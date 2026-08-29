<?php

/**
 * The only refusal wrapper the wire layer throws or accepts.
 *
 * Mirrors the contract's typed host-port failure: keeping the canonical
 * HostError as the single public member stops transports and hosts from
 * leaking implementation exceptions, stack traces, or private state across
 * the authority boundary. The PHP exception message is the catalog key (or
 * its bounded pre-written fallback) — structurally incapable of carrying
 * raw internals. A host may explicitly mark a refusal as a committed
 * mutation outcome when safe failure state and audit must be durable; the
 * atomic mutation boundary is the only component that consumes that signal.
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
 * message carries no internals. {@see $commitsState} is an explicit host
 * assertion, not an inference from the error category.
 *
 * @since   0.1.0
 */
final class HostRefusal extends \RuntimeException
{
    /**
     * Wraps one canonical refusal; the exception message is derived from
     * the error's bounded message reference, never supplied separately.
     *
     * @param   HostError  $error         The canonical refusal being thrown.
     * @param   bool       $commitsState  True only when the mutation's safe
     *                                    failed state, audit, and refusal must
     *                                    commit and replay together.
     *
     * @since   0.1.0
     */
    public function __construct(
        private readonly HostError $error,
        private readonly bool $commitsState = false,
    ) {
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

    /**
     * Whether this refusal is the intended logical outcome of a mutation
     * whose safe failed state must commit.
     *
     * A true value is meaningful only inside
     * {@see \Kumwe\Producer\Wire\Port\MutationBoundaryInterface::execute()}.
     * Ordinary refusals remain false and roll back the atomic callback.
     *
     * @return  bool  True only for an explicitly committed refusal.
     *
     * @since   0.2.0
     */
    public function commitsState(): bool
    {
        return $this->commitsState;
    }
}
