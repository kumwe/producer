<?php

/**
 * The only refusal wrapper the wire layer throws or accepts.
 *
 * Mirrors the contract's typed host-port failure: keeping the canonical
 * HostError as the single public member stops transports and hosts from
 * leaking implementation exceptions, stack traces, or private state across
 * the authority boundary. The PHP exception message is the catalog key (or
 * its bounded pre-written fallback) — structurally incapable of carrying
 * raw internals.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Error;

/**
 * The typed refusal exception: exactly one canonical {@see HostError} and
 * nothing else.
 *
 * This is the only exception the wire layer throws to refuse, and the only
 * one it lets pass through a host port; any other throwable is converted
 * to a non-disclosing internal refusal. The exception message is the
 * error's pre-written fallback (or its catalog key), so even a logged
 * message carries no internals.
 *
 * @since   0.1.0
 */
final class HostRefusal extends \RuntimeException
{
    /**
     * Wraps one canonical refusal; the exception message is derived from
     * the error's bounded message reference, never supplied separately.
     *
     * @param   HostError  $error  The canonical refusal being thrown.
     *
     * @since   0.1.0
     */
    public function __construct(private readonly HostError $error)
    {
        parent::__construct($error->message()->defaultMessage() ?? $error->message()->key());
    }

    /**
     * The canonical refusal to serialize onto the wire.
     *
     * @return  HostError  The wrapped error, valid by construction.
     *
     * @since   0.1.0
     */
    public function error(): HostError
    {
        return $this->error;
    }
}
