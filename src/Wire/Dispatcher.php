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

/**
 * The wire entry point: one call from raw route and body to a finished
 * {@see Response}, with every decision the host's and every failure a
 * typed, non-disclosing refusal.
 *
 * The fixed stage order in the file header is the contract: registry
 * resolution, envelope validation and registry cross-checks, the host's
 * authorization first for every operation, idempotent replay of recorded
 * outcomes without re-applying, the port call, then result discipline.
 * {@see dispatch()} never throws and never leaks an internal — whatever
 * happens, the caller receives canonical bytes from the closed taxonomy.
 *
 * @since   0.1.0
 */
final class Dispatcher
{
    /**
     * Binds the host authority the dispatch stages consult.
     *
     * @param   HostAdapterInterface  $host              The host's ports,
     *                                                   authorization, and
     *                                                   idempotency ledger.
     * @param   StrictResponder       $responder         Serializes outcomes to
     *                                                   canonical bytes.
     * @param   int                   $maximumBodyBytes  The request body bound
     *                                                   handed to
     *                                                   {@see RequestEnvelope::parse()};
     *                                                   host policy, 1 MiB by
     *                                                   default.
     *
     * @since   0.1.0
     */
    public function __construct(
        private readonly HostAdapterInterface $host,
        private readonly StrictResponder $responder = new StrictResponder(),
        private readonly int $maximumBodyBytes = RequestEnvelope::DEFAULT_MAXIMUM_BODY_BYTES,
    ) {
    }

    /**
     * Serves one request end to end. Never throws: a {@see HostRefusal}
     * from any stage serializes verbatim, and any other throwable becomes
     * a non-disclosing internal refusal — so every outcome, success or
     * refusal, leaves as canonical bytes with the strict headers.
     *
     * @param   string  $route  The transport route addressed, e.g.
     *                          `artifact/save`.
     * @param   string  $body   The raw request body bytes.
     *
     * @return  Response  The canonical response, result or refusal.
     *
     * @since   0.1.0
     */
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

    /**
     * The dispatch stages in their fixed order: registry resolution,
     * envelope parsing, the registry cross-checks (route agreement,
     * expectedRevision exactly on concurrency-protected operations, an
     * idempotency key only on a mutating one), authorization, replay,
     * port call, result discipline, and — for a keyed mutation — the
     * ledger write before the response is released.
     *
     * @param   string  $route  The transport route addressed.
     * @param   string  $body   The raw request body bytes.
     *
     * @return  Response  The canonical response for the accepted outcome.
     *
     * @throws  HostRefusal  From any stage; {@see dispatch()} serializes
     *                       it.
     *
     * @since   0.1.0
     */
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

    /**
     * Asks the host's authorization first — before replay, before the
     * port. A returned HostError is thrown to be emitted verbatim; a
     * thrown {@see HostRefusal} passes through; any other throwable fails
     * closed as internal, because no decision means no.
     *
     * @param   Operation        $operation  The resolved registry row.
     * @param   RequestEnvelope  $envelope   The validated request, argument
     *                                       included — authorization is
     *                                       item-scoped.
     *
     * @throws  HostRefusal  When the host refuses or cannot decide.
     *
     * @since   0.1.0
     */
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

    /**
     * Recalls the ledger record for a scope key. A ledger that cannot
     * answer fails closed as internal: when replay cannot be ruled out,
     * the mutation must not run.
     *
     * @param   string  $scopeKey  The canonical scope key digest.
     *
     * @return  IdempotencyRecord|null  The accepted outcome, or null when
     *                                  the key is unseen.
     *
     * @throws  HostRefusal  When the ledger fails to answer.
     *
     * @since   0.1.0
     */
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

    /**
     * Records the accepted outcome of a keyed mutation in the ledger. A
     * failed write is an internal refusal: an outcome that cannot be
     * recorded for replay is not released.
     *
     * @param   string             $scopeKey  The canonical scope key digest.
     * @param   IdempotencyRecord  $record    The intent digest and result to
     *                                        store.
     *
     * @throws  HostRefusal  When the ledger write fails.
     *
     * @since   0.1.0
     */
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

    /**
     * Calls the operation's method on the host's port with the argument
     * passed through unjudged (null when absent). A thrown
     * {@see HostRefusal} passes verbatim; any other throwable becomes a
     * non-disclosing internal refusal.
     *
     * @param   Operation        $operation  The resolved registry row.
     * @param   RequestEnvelope  $envelope   The validated request.
     *
     * @return  HostResult  The port's proven outcome.
     *
     * @throws  HostRefusal  The port's typed refusal, or internal for
     *                       anything else it threw.
     *
     * @since   0.1.0
     */
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

    /**
     * Result discipline, then serialization: a concurrency-protected
     * operation that returned no advanced revision fails closed as
     * internal — the host broke the contract, and the caller must not
     * receive an unversioned acceptance.
     *
     * @param   Operation   $operation  The resolved registry row.
     * @param   HostResult  $result     The outcome to serialize.
     *
     * @return  Response  The canonical result response.
     *
     * @throws  HostRefusal  internal when the required revision is
     *                       missing, or from the responder when the result
     *                       cannot take canonical form.
     *
     * @since   0.1.0
     */
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

    /**
     * Resolves the operation's port on the host. The match is closed over
     * the registry's ten port names; an absent optional port is refused as
     * unavailable with retryable false, never guessed at.
     *
     * @param   Operation  $operation  The resolved registry row.
     *
     * @return  object  The host's port implementation.
     *
     * @throws  HostRefusal  unavailable when the host does not provide the
     *                       port.
     *
     * @since   0.1.0
     */
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

    /**
     * The deterministic ledger key of a keyed mutation: the canonical
     * digest of exactly (idempotencyKey, operationId, resourceContextKey,
     * sessionGeneration), per the pinned host sequence vectors.
     *
     * @param   Operation       $operation  The resolved registry row.
     * @param   RequestContext  $context    The validated context carrying
     *                                      the key.
     *
     * @return  string  The canonical scope key digest.
     *
     * @since   0.1.0
     */
    private static function scopeKey(Operation $operation, RequestContext $context): string
    {
        $scope = new \stdClass();
        $scope->idempotencyKey = $context->idempotencyKey;
        $scope->operationId = $operation->capability;
        $scope->resourceContextKey = $context->resourceContextKey;
        $scope->sessionGeneration = $context->sessionGeneration;

        return CanonicalJson::digest($scope);
    }

    /**
     * The deterministic fingerprint of what the caller asked for: the
     * canonical digest of the argument (when present) plus
     * expectedRevision, locale, and protocolVersion, absent optionals
     * omitted — the identity a retry must match to replay.
     *
     * @param   RequestEnvelope  $envelope  The validated request.
     *
     * @return  string  The canonical intent digest.
     *
     * @since   0.1.0
     */
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

    /**
     * The cross-check refusal path: a fixed invalid-request built from a
     * catalog reference, echoing nothing.
     *
     * @param   string  $key             The catalog key of the refusal
     *                                   message.
     * @param   string  $defaultMessage  Its pre-written fallback.
     *
     * @throws  HostRefusal  Always — invalid-request.
     *
     * @since   0.1.0
     */
    private static function refuseInvalid(string $key, string $defaultMessage): never
    {
        throw new HostRefusal(HostError::invalidRequest(new MessageReference($key, $defaultMessage)));
    }
}
