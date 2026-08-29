<?php

/**
 * One logically exact outcome returned by the host mutation boundary.
 *
 * This is deliberately not a storage schema. The host owns encryption,
 * redaction, capability handles, retention, and replay rehydration. It
 * returns only the proved logical outcome Producer is allowed to emit,
 * which prevents a wire result containing a secret capability from
 * becoming a requirement to persist that capability in plaintext.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire;

use Kumwe\Producer\Error\HostError;

/**
 * The logical value returned after a host mutation transaction completes.
 *
 * A keyed outcome carries the accepted Producer intent digest; an unkeyed
 * outcome carries null. {@see Dispatcher} verifies that coordinate and
 * emits {@see outcome()}. The outcome can be a success or an explicitly
 * committed refusal. How the host safely stored and rehydrated it remains
 * outside this value and outside Producer.
 *
 * @since   0.2.0
 */
final class MutationOutcome
{
    /**
     * Bind one proved logical outcome to its optional replay intent.
     *
     * @param   string|null           $intentDigest  SRI SHA-256 of the
     *                                                  accepted semantic
     *                                                  request intent, or
     *                                                  null when unkeyed.
     * @param   HostResult|HostError  $outcome       Rehydrated logical
     *                                                  success or committed
     *                                                  refusal.
     *
     * @throws  \InvalidArgumentException  When a supplied digest is invalid.
     *
     * @since   0.2.0
     */
    public function __construct(
        public readonly ?string $intentDigest,
        private readonly HostResult|HostError $outcome,
    ) {
        if (
            $intentDigest !== null
            && preg_match('/^sha256-[A-Za-z0-9+\/]{42}[AEIMQUYcgkosw048]=\z/', $intentDigest) !== 1
        ) {
            throw new \InvalidArgumentException('A keyed mutation outcome needs a canonical SHA-256 digest.');
        }
    }

    /**
     * Return the host-rehydrated logical outcome.
     *
     * @return  HostResult|HostError  The committed success or refusal.
     *
     * @since   0.2.0
     */
    public function outcome(): HostResult|HostError
    {
        return $this->outcome;
    }
}
