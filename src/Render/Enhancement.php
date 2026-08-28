<?php

/**
 * One progressive-behavior request the rendered page carries.
 *
 * Rendering never generates code: an enhancement only names a behavior from
 * Studio's prebuilt, versioned runtime, the node that wants it, and the
 * bounded configuration that runtime needs. The host decides whether to load
 * the runtime at all; the no-JavaScript markup stays fully usable without it.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render;

final class Enhancement
{
    /**
     * @param array<string, mixed> $details behavior-specific configuration,
     *     mirroring the reference enhancement payload members
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $nodeId,
        public readonly string $scope,
        public readonly array $details = [],
    ) {
    }
}
