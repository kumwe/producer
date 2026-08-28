<?php

/**
 * The permission port, studio.port/permission.
 *
 * Explanations and snapshots only — decisions themselves come through
 * {@see AuthorizationInterface} on every call, and a Studio-visible
 * permission list is display context, never authority. Refusal is a
 * thrown {@see HostRefusal}.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\RequestContext;

interface PermissionPortInterface
{
    /**
     * studio.operation/permission.explain — whether an operation would be
     * allowed, with an optional reason.
     *
     * @throws HostRefusal
     */
    public function explain(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/permission.refresh — the current permission
     * snapshot and session generation; takes only the envelope.
     *
     * @throws HostRefusal
     */
    public function refresh(mixed $arguments, RequestContext $context): HostResult;
}
