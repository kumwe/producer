<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * One progressive-behavior request the rendered page carries.
 *
 * Rendering never generates code: an enhancement only names a behavior from
 * Studio's prebuilt, versioned runtime, the node that wants it, and the
 * bounded configuration that runtime needs. The host decides whether to load
 * the runtime at all; the no-JavaScript markup stays fully usable without it.
 * Immutable once constructed.
 *
 * @since   0.1.0
 */
final class Enhancement
{
    /**
     * @param   string  $kind    The behavior name from the reference
     *     runtime's closed vocabulary (e.g. motion, countdown).
     * @param   string  $nodeId  The requesting node's stored id.
     * @param   string  $scope   The requesting node's CSS-safe scope token.
     * @param   array<string, mixed>  $details  Behavior-specific
     *     configuration, mirroring the reference enhancement payload
     *     members.
     * @since   0.1.0
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $nodeId,
        public readonly string $scope,
        public readonly array $details = [],
    ) {
    }
}
