<?php

/**
 * The telemetry port, studio.port/telemetry.
 *
 * Named events with primitive attributes, under the host's privacy
 * policy. Refusal is a thrown {@see HostRefusal}; a non-primitive
 * attribute is validation-failed per the pinned host vectors.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\RequestContext;

interface TelemetryPortInterface
{
    /**
     * studio.operation/telemetry.emit — record one telemetry event; the
     * result value is null.
     *
     * @throws HostRefusal
     */
    public function emit(mixed $arguments, RequestContext $context): HostResult;
}
