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
 * 4. Host-atomic execution for every mutation. A keyed mutation adds a
 *    scope digest of (idempotencyKey, operationId, resourceContextKey,
 *    sessionGeneration) and an intent digest of the argument plus
 *    expectedRevision, locale, and protocolVersion. A matching record
 *    replays the protected, rehydrated outcome without touching the port;
 *    changed intent is refused as invalid-request. An unkeyed mutation has
 *    no replay coordinates but keeps the same transaction and audit
 *    boundary. The host adds trusted actor/session scope and owns storage.
 * 5. The port call, on the host's implementation. An absent optional port
 *    is refused as unavailable (retryable false). A thrown
 *    {@see HostRefusal} passes through verbatim; any other throwable
 *    becomes a non-disclosing internal refusal.
 * 6. Result discipline: a concurrency-protected operation must return the
 *    advanced revision (fail closed as internal otherwise). A keyed result
 *    is released only after the host's atomic boundary has committed it.
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
     *                                                   atomic mutation boundary.
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
     * idempotency key only on a mutating one), authorization, host-atomic
     * execution or replay, port call, and result discipline.
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

        if ($operation->mutating) {
            return $this->executeMutation($operation, $envelope);
        }

        $result = $this->invoke($operation, $envelope);

        return $this->respond($operation, $result);
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
     * Hand every mutation to the host's single atomic execution boundary.
     *
     * Producer supplies deterministic replay digests only when the request
     * is keyed. The host adds trusted actor/session scope, replays a
     * completed logical outcome when present, or commits the mutation and
     * audit together. It owns protected storage and rehydration. Any failure
     * rolls back and fails closed; unkeyed mutations retain the same atomic
     * transaction and audit guarantee without creating a replay identity.
     *
     * @param   Operation        $operation  The resolved mutating registry row.
     * @param   RequestEnvelope  $envelope   Validated mutation request.
     *
     * @return  Response  Newly committed or exactly replayed result.
     *
     * @throws  HostRefusal  When execution, storage, or replay fails.
     *
     * @since   0.2.0
     */
    private function executeMutation(Operation $operation, RequestEnvelope $envelope): Response
    {
        $keyed = $envelope->context()->idempotencyKey !== null;
        $scopeKey = $keyed ? self::scopeKey($operation, $envelope->context()) : null;
        $intentDigest = $keyed ? self::intentDigest($envelope) : null;

        try {
            $record = $this->host->mutations()->execute(
                $operation,
                $envelope,
                $scopeKey,
                $intentDigest,
                function () use ($operation, $envelope): HostResult|HostError {
                    try {
                        $result = $this->invoke($operation, $envelope);
                        $this->assertResult($operation, $result);

                        return $result;
                    } catch (HostRefusal $refusal) {
                        if (!$refusal->commitsState()) {
                            throw $refusal;
                        }

                        return $refusal->error();
                    }
                },
            );
        } catch (HostRefusal $thrown) {
            throw $thrown;
        } catch (\Throwable) {
            throw new HostRefusal(HostError::internal(new MessageReference(
                'kumwe.producer/mutation-transaction-failed',
                'The mutation, audit, and replay state could not be committed atomically.'
            )));
        }

        if (($record->intentDigest === null) !== ($intentDigest === null)) {
            throw new HostRefusal(HostError::internal(new MessageReference(
                'kumwe.producer/mutation-coordinate-invalid',
                'The host returned a mutation outcome with invalid replay coordinates.'
            )));
        }
        if (
            $intentDigest !== null
            && $record->intentDigest !== null
            && !hash_equals($record->intentDigest, $intentDigest)
        ) {
            self::refuseInvalid(
                'kumwe.producer/idempotent-intent-changed',
                'This idempotency key was accepted for a different request.'
            );
        }

        $outcome = $record->outcome();
        if ($outcome instanceof HostError) {
            return $this->responder->refusal($outcome);
        }

        return $this->respond($operation, $outcome);
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
        $this->assertResult($operation, $result);

        return $this->responder->result($result);
    }

    /**
     * Enforce operation-specific result discipline before commit or output.
     *
     * This check runs inside the host's atomic callback for a keyed
     * mutation, so an invalid result aborts its authoritative transaction
     * rather than leaving an unreplayable accepted effect behind.
     *
     * @param   Operation   $operation  The resolved registry row.
     * @param   HostResult  $result     The host outcome to prove.
     *
     * @return  void
     *
     * @throws  HostRefusal  When a protected mutation omits its revision.
     *
     * @since   0.2.0
     */
    private function assertResult(Operation $operation, HostResult $result): void
    {
        if ($operation->expectsRevision && $result->revision === null) {
            throw new HostRefusal(HostError::internal(new MessageReference(
                'kumwe.producer/missing-revision',
                'The host accepted a concurrency-protected mutation without returning the advanced revision.'
            )));
        }
    }

    /**
     * Resolves the operation's port on the host. The match is closed over
     * the registry's nine port names; an absent optional port is refused as
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
     * The deterministic replay scope key of a keyed mutation: the canonical
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
