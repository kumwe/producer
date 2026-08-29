<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * Closed behavior for unresolved block coordinates.
 *
 * @since   0.2.0
 */
enum RenderPolicy: string
{
    /**
     * Draft/preview behavior required by renderer-web conformance.
     *
     * @since   0.2.0
     */
    case Fallback = 'fallback';

    /**
     * Published behavior: every lock and node must resolve exactly.
     *
     * @since   0.2.0
     */
    case RequireRegistered = 'require-registered';
}
