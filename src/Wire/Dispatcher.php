<?php

/**
 * Drive one validated request through the host's authority to canonical
 * response bytes.
 *
 * The dispatcher decides nothing itself. It proves the request is the
 * pinned contract's shape, then hands every decision to the host in a
 * fixed order:
 *
 * 1. Resolve the transport route in the closed registry — unknown port or
 *    operation is a typed invalid-request refusal, never a passthrough.
 * 2. Parse and validate the envelope ({@see RequestEnvelope::parse()}),
 *    and cross-check it against the operation's registry row: the
 *    envelope must name the routed operation, a concurrency-protected
 *    operation must carry expectedRevision (and only such an operation
 *    may), and only a mutating operation may carry an idempotency key.
 * 3. Authorization first, from the host, for every operation. A returned
 *    HostError is emitted verbatim; an authorization failure of any other
 *    kind fails closed as internal. Nothing later runs after a refusal.
 * 4. Idempotent replay for a keyed mutation: the scope key digests
 *    (idempotencyKey, operationId, resourceContextKey,
 *    sessionGeneration); the intent digest fingerprints the argument
 *    plus expectedRevision, locale, and protocolVersion with absent
 *    optionals omitted. A matching record replays the stored outcome
 *    without touching the port; a key reused with changed intent is
 *    refused as invalid-request — the pinned sequence vectors' exact
 *    policy.
 * 5. The port call, on the host's implementation. An absent optional port
 *    is refused as unavailable (retryable false). A thrown
 *    {@see HostRefusal} passes through verbatim; any other throwable
 *    becomes a non-disclosing internal refusal.
 * 6. Result discipline: a concurrency-protected operation must return the
 *    advanced revision (fail closed as internal otherwise), and the
 *    accepted outcome of a keyed mutation is recorded in the ledger
 *    before the response is released.
 *
 * The registry does not say which operations take an argument, so the
 * dispatcher passes the argument (or null when absent) through unjudged;
 * argument semantics beyond the jsonValue shape are the host's to refuse
 * as validation-failed.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Error\HostError;
use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Error\MessageReference;
use Kumwe\Producer\Wire\Port\HostAdapterInterface;

final class Dispatcher
{
    public function __construct(
        private readonly HostAdapterInterface $host,
        private readonly StrictResponder $responder = new StrictResponder(),
        private readonly int $maximumBodyBytes = RequestEnvelope::DEFAULT_MAXIMUM_BODY_BYTES,
    ) {
    }

    public function dispatch(string $route, string $body): Response
    {
        try {
            return $this->handle($route, $body);
        } catch (HostRefusal $refusal) {
            return $this->responder->refusal($refusal->error());
        } catch (\Throwable) {
            return $this->responder->refusal(HostError::internal(new MessageReference(
                'kumwe.producer/internal',
                'The host could not complete the operation.'
            )));
        }
    }

    private function handle(string $route, string $body): Response
    {
        $operation = OperationRegistry::byRoute($route);
        $envelope = RequestEnvelope::parse($body, $this->maximumBodyBytes);
        $context = $envelope->context();

        if ($context->operationId !== $operation->capability) {
            self::refuseInvalid(
                'kumwe.producer/operation-mismatch',
                'The envelope names a different operation than the transport route addresses.'
            );
        }
        if ($operation->expectsRevision && $context->expectedRevision === null) {
            self::refuseInvalid(
                'kumwe.producer/expected-revision-required',
                'This operation is concurrency-protected and requires expectedRevision.'
            );
        }
        if (!$operation->expectsRevision && $context->expectedRevision !== null) {
            self::refuseInvalid(
                'kumwe.producer/expected-revision-unexpected',
                'Only a concurrency-protected operation carries expectedRevision.'
            );
        }
        if (!$operation->mutating && $context->idempotencyKey !== null) {
            self::refuseInvalid(
                'kumwe.producer/idempotency-key-unexpected',
                'Only a mutating operation carries an idempotency key.'
            );
        }

        $this->authorize($operation, $envelope);

        $scopeKey = null;
        $intentDigest = null;
        if ($operation->mutating && $context->idempotencyKey !== null) {
            $scopeKey = self::scopeKey($operation, $context);
            $intentDigest = self::intentDigest($envelope);
            $record = $this->recall($scopeKey);
            if ($record !== null) {
                if ($record->intentDigest !== $intentDigest) {
                    self::refuseInvalid(
                        'kumwe.producer/idempotent-intent-changed',
                        'This idempotency key was accepted for a different request.'
                    );
                }

                return $this->respond($operation, $record->result);
            }
        }

        $result = $this->invoke($operation, $envelope);
        $response = $this->respond($operation, $result);

        if ($scopeKey !== null && $intentDigest !== null) {
            $this->record($scopeKey, new IdempotencyRecord($intentDigest, $result));
        }

        return $response;
    }

    private function authorize(Operation $operation, RequestEnvelope $envelope): void
    {
        try {
            $refusal = $this->host->authorization()->authorize($operation, $envelope);
        } catch (HostRefusal $thrown) {
            throw $thrown;
        } catch (\Throwable) {
            throw new HostRefusal(HostError::internal(new MessageReference(
                'kumwe.producer/authorization-failed',
                'No authorization decision could be obtained, so the request is refused.'
            )));
        }
        if ($refusal instanceof HostError) {
            throw new HostRefusal($refusal);
        }
    }

    private function recall(string $scopeKey): ?IdempotencyRecord
    {
        try {
            return $this->host->idempotency()->recall($scopeKey);
        } catch (HostRefusal $thrown) {
            throw $thrown;
        } catch (\Throwable) {
            throw new HostRefusal(HostError::internal(new MessageReference(
                'kumwe.producer/idempotency-unavailable',
                'Replay of this mutation could not be ruled out, so the request is refused.'
            )));
        }
    }

    private function record(string $scopeKey, IdempotencyRecord $record): void
    {
        try {
            $this->host->idempotency()->record($scopeKey, $record);
        } catch (HostRefusal $thrown) {
            throw $thrown;
        } catch (\Throwable) {
            throw new HostRefusal(HostError::internal(new MessageReference(
                'kumwe.producer/idempotency-record-failed',
                'The mutation outcome could not be recorded for replay.'
            )));
        }
    }

    private function invoke(Operation $operation, RequestEnvelope $envelope): HostResult
    {
        $port = $this->port($operation);
        $arguments = $envelope->hasArguments() ? $envelope->arguments() : null;
        try {
            return $port->{$operation->method}($arguments, $envelope->context());
        } catch (HostRefusal $thrown) {
            throw $thrown;
        } catch (\Throwable) {
            throw new HostRefusal(HostError::internal(new MessageReference(
                'kumwe.producer/operation-failed',
                'The operation failed inside the host.'
            )));
        }
    }

    private function respond(Operation $operation, HostResult $result): Response
    {
        if ($operation->expectsRevision && $result->revision === null) {
            throw new HostRefusal(HostError::internal(new MessageReference(
                'kumwe.producer/missing-revision',
                'The host accepted a concurrency-protected mutation without returning the advanced revision.'
            )));
        }

        return $this->responder->result($result);
    }

    private function port(Operation $operation): object
    {
        $port = match ($operation->port) {
            'artifact' => $this->host->artifact(),
            'authoring' => $this->host->authoring(),
            'localization' => $this->host->localization(),
            'media' => $this->host->media(),
            'model' => $this->host->model(),
            'permission' => $this->host->permission(),
            'preview' => $this->host->preview(),
            'recovery' => $this->host->recovery(),
            'resource' => $this->host->resource(),
            'telemetry' => $this->host->telemetry(),
        };
        if ($port === null) {
            throw new HostRefusal(HostError::unavailable(new MessageReference(
                'kumwe.producer/port-unavailable',
                'This host does not provide the port the operation belongs to.'
            )));
        }

        return $port;
    }

    private static function scopeKey(Operation $operation, RequestContext $context): string
    {
        $scope = new \stdClass();
        $scope->idempotencyKey = $context->idempotencyKey;
        $scope->operationId = $operation->capability;
        $scope->resourceContextKey = $context->resourceContextKey;
        $scope->sessionGeneration = $context->sessionGeneration;

        return CanonicalJson::digest($scope);
    }

    private static function intentDigest(RequestEnvelope $envelope): string
    {
        $context = $envelope->context();
        $intent = new \stdClass();
        if ($envelope->hasArguments()) {
            $intent->argument = $envelope->arguments();
        }
        if ($context->expectedRevision !== null) {
            $intent->expectedRevision = $context->expectedRevision;
        }
        if ($context->locale !== null) {
            $intent->locale = $context->locale;
        }
        $intent->protocolVersion = $context->protocolVersion;

        return CanonicalJson::digest($intent);
    }

    private static function refuseInvalid(string $key, string $defaultMessage): never
    {
        throw new HostRefusal(HostError::invalidRequest(new MessageReference($key, $defaultMessage)));
    }
}
