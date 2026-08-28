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

final class HostRefusal extends \RuntimeException
{
    public function __construct(private readonly HostError $error)
    {
        parent::__construct($error->message()->defaultMessage() ?? $error->message()->key());
    }

    public function error(): HostError
    {
        return $this->error;
    }
}
