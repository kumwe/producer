<?php

/**
 * The model port, studio.port/model.
 *
 * Read access to content model documents. Refusal is a thrown
 * {@see HostRefusal}.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\RequestContext;

interface ModelPortInterface
{
    /**
     * studio.operation/model.get — one content model document.
     *
     * @throws HostRefusal
     */
    public function get(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/model.list — the content model documents in scope;
     * takes only the envelope.
     *
     * @throws HostRefusal
     */
    public function list(mixed $arguments, RequestContext $context): HostResult;
}
