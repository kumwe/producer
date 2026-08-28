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

interface RecoveryPortInterface
{
    /**
     * studio.operation/recovery.discard — drop the stored envelope; takes
     * only the envelope.
     *
     * @throws HostRefusal
     */
    public function discard(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/recovery.load — the stored envelope, or null when
     * absent; takes only the envelope.
     *
     * @throws HostRefusal
     */
    public function load(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/recovery.store — store one recovery envelope.
     *
     * @throws HostRefusal
     */
    public function store(mixed $arguments, RequestContext $context): HostResult;
}
