<?php

/**
 * The resource port, studio.port/resource.
 *
 * Bounded host resource search for reference pickers. Refusal is a thrown
 * {@see HostRefusal}.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\RequestContext;

interface ResourcePortInterface
{
    /**
     * studio.operation/resource.search — a search page for a query.
     *
     * @throws HostRefusal
     */
    public function search(mixed $arguments, RequestContext $context): HostResult;
}
