<?php

/**
 * The preview port, studio.port/preview.
 *
 * Draft rendering for the authoring surface. Refusal is a thrown
 * {@see HostRefusal}; a render superseded by cancellation is cancelled,
 * retryable false, per the pinned sequence vectors.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\RequestContext;

interface PreviewPortInterface
{
    /**
     * studio.operation/preview.cancel — cancel outstanding renders for a
     * draft digest.
     *
     * @throws HostRefusal
     */
    public function cancel(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/preview.render — render one draft; the result is
     * the rendered payload.
     *
     * @throws HostRefusal
     */
    public function render(mixed $arguments, RequestContext $context): HostResult;
}
