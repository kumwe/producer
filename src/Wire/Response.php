<?php

/**
 * The exact bytes and header list of one wire response.
 *
 * Producer never emits: the host writes these bytes and headers with its
 * own transport, status policy, and middleware. The contract distinguishes
 * outcomes by body shape — a host-result document or a host-error
 * document — never by status code. The typed refusal category lets the
 * host choose transport status without parsing canonical JSON.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire;

use Kumwe\Producer\Error\HostError;

/**
 * One finished wire response: canonical body bytes, the exact headers to
 * send with them, and whether the body is a refusal.
 *
 * The host must emit the body bytes verbatim — they are canonical JSON and
 * any re-encoding breaks byte identity — together with every header in the
 * list. No status code is carried because transport policy belongs to the
 * host. A nullable category is the stable signal for that policy; the
 * derived refusal flag remains a convenient shape discriminator.
 *
 * @since   0.1.0
 */
final class Response
{
    /**
     * Whether the body is a host-error document.
     *
     * @since   0.1.0
     */
    public readonly bool $refusal;

    /**
     * Binds the finished response; construction happens only inside
     * {@see StrictResponder}, which guarantees the body is canonical.
     *
     * @param   string                 $body     The canonical JSON bytes to
     *                                           emit verbatim.
     * @param   array<string, string>  $headers  Lowercase header names.
     * @param   string|null            $refusalCategory  Stable host-error
     *                                                     category, or null
     *                                                     for a result.
     *
     * @since   0.1.0
     */
    public function __construct(
        public readonly string $body,
        public readonly array $headers,
        public readonly ?string $refusalCategory = null,
    ) {
        if ($refusalCategory !== null && !in_array($refusalCategory, HostError::CATEGORIES, true)) {
            throw new \InvalidArgumentException('A response refusal category must belong to the host taxonomy.');
        }
        $this->refusal = $refusalCategory !== null;
    }
}
