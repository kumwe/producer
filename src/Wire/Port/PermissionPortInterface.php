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

/**
 * Permission explanations and snapshots for display, optional — never
 * authority.
 *
 * What the host receives: only calls the dispatcher has already
 * validated, cross-checked against the registry row, and had allowed by
 * the host's own {@see AuthorizationInterface}. What the host must
 * guarantee back: a {@see HostResult} carrying the schema-shaped
 * explanation or snapshot, refusing only by throwing {@see HostRefusal}
 * with a taxonomy category and a non-disclosing message — and that
 * nothing it returns here is ever treated as an authorization decision:
 * decisions come through {@see AuthorizationInterface} on every call, and
 * a Studio-visible permission list is display context only.
 *
 * @since   0.1.0
 */
interface PermissionPortInterface
{
    /**
     * studio.operation/permission.explain — whether an operation would be
     * allowed, with an optional reason.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The explanation document — a prediction for
     *                      display, never a grant.
     *
     * @throws  HostRefusal  A taxonomy refusal.
     *
     * @since   0.1.0
     */
    public function explain(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/permission.refresh — the current permission
     * snapshot and session generation; takes only the envelope.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The snapshot document carrying the current
     *                      session generation.
     *
     * @throws  HostRefusal  A taxonomy refusal.
     *
     * @since   0.1.0
     */
    public function refresh(mixed $arguments, RequestContext $context): HostResult;
}
