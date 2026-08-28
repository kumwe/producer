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

final class RequestContext
{
    /**
     * @param array<string, string> $traceContext
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
