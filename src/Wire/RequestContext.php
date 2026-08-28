<?php

/**
 * The validated request envelope context, host-request.schema.json.
 *
 * Actor identity and authorization evidence never appear here: the trusted
 * transport attaches them, and the host re-derives authority per request.
 * Instances are produced by {@see RequestEnvelope::parse()}, which is the
 * validating path; the constructor itself performs no checks.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire;

/**
 * The envelope's context members after validation, as plain readonly
 * state.
 *
 * Every member has already been proven against its contract grammar by
 * {@see RequestEnvelope::parse()} — the constructor checks nothing, so
 * construct instances only on that path. Deliberately absent: actor
 * identity and authorization evidence, which the trusted transport
 * attaches out of band and the host re-derives per request.
 *
 * @since   0.1.0
 */
final class RequestContext
{
    /**
     * Binds the validated members verbatim; validation lives in
     * {@see RequestEnvelope::parse()}, not here.
     *
     * @param   string                 $operationId         The qualified name of the operation
     *                                                      the envelope addresses, proven to be
     *                                                      in the closed registry.
     * @param   string                 $protocolVersion     The negotiated wire version, proven
     *                                                      equal to the supported pin.
     * @param   string                 $requestId           The caller's stable id for this
     *                                                      request.
     * @param   string                 $resourceContextKey  The stable key of the resource scope
     *                                                      the request runs in.
     * @param   string                 $sessionGeneration   The permission snapshot generation
     *                                                      the caller holds.
     * @param   string|null            $expectedRevision    The revision a concurrency-protected
     *                                                      mutation expects, or null.
     * @param   string|null            $idempotencyKey      The caller's replay key for a
     *                                                      mutation, or null.
     * @param   string|null            $locale              The caller's locale tag, or null.
     * @param   array<string, string>  $traceContext        At most 10 bounded tracing entries
     *                                                      under local names.
     *
     * @since   0.1.0
     */
    public function __construct(
        public readonly string $operationId,
        public readonly string $protocolVersion,
        public readonly string $requestId,
        public readonly string $resourceContextKey,
        public readonly string $sessionGeneration,
        public readonly ?string $expectedRevision = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $locale = null,
        public readonly array $traceContext = [],
    ) {
    }
}
