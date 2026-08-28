<?php

/**
 * The recovery port, studio.port/recovery.
 *
 * A per-context recovery envelope the host stores durably on the caller's
 * behalf — the storage is the host's, per the charter. Refusal is a
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
 * Durable per-context recovery storage on the caller's behalf, optional —
 * the storage is the host's, per the charter.
 *
 * What the host receives: only calls the dispatcher has already
 * validated, cross-checked against the registry row, and had allowed by
 * the host's own {@see AuthorizationInterface}; the resource context key
 * in the validated context scopes which envelope is meant. What the host
 * must guarantee back: a {@see HostResult} carrying the schema-shaped
 * outcome, refusing only by throwing {@see HostRefusal} with a taxonomy
 * category and a non-disclosing message. Store and discard are mutations
 * and may carry an idempotency key; the dispatcher then replays their
 * recorded outcomes without re-invoking the port.
 *
 * @since   0.1.0
 */
interface RecoveryPortInterface
{
    /**
     * studio.operation/recovery.discard — drop the stored envelope; takes
     * only the envelope.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context;
     *                                      its resource context key names
     *                                      the envelope to drop.
     *
     * @return  HostResult  The discard acknowledgement.
     *
     * @throws  HostRefusal  A taxonomy refusal.
     *
     * @since   0.1.0
     */
    public function discard(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/recovery.load — the stored envelope, or null when
     * absent; takes only the envelope.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context;
     *                                      its resource context key names
     *                                      the envelope to load.
     *
     * @return  HostResult  The stored recovery envelope, or an explicit
     *                      null value when none is stored.
     *
     * @throws  HostRefusal  A taxonomy refusal.
     *
     * @since   0.1.0
     */
    public function load(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/recovery.store — store one recovery envelope.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context;
     *                                      its resource context key scopes
     *                                      the stored envelope.
     *
     * @return  HostResult  The store acknowledgement.
     *
     * @throws  HostRefusal  A taxonomy refusal — e.g. limit-exceeded for
     *                       an envelope beyond the host's storage bound.
     *
     * @since   0.1.0
     */
    public function store(mixed $arguments, RequestContext $context): HostResult;
}
