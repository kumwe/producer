<?php

/**
 * The exact bytes and header list of one wire response.
 *
 * Producer never emits: the host writes these bytes and headers with its
 * own transport, status policy, and middleware. The contract distinguishes
 * outcomes by body shape — a host-result document or a host-error
 * document — never by status code, so this value carries no status; the
 * refusal flag tells the host which shape it is holding.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire;

/**
 * One finished wire response: canonical body bytes, the exact headers to
 * send with them, and whether the body is a refusal.
 *
 * The host must emit the body bytes verbatim — they are canonical JSON and
 * any re-encoding breaks byte identity — together with every header in the
 * list. No status code is carried because the contract distinguishes
 * outcomes by body shape alone; the refusal flag exists solely so the host
 * can apply its own status policy without inspecting the body.
 *
 * @since   0.1.0
 */
final class Response
{
    /**
     * Binds the finished response; construction happens only inside
     * {@see StrictResponder}, which guarantees the body is canonical.
     *
     * @param   string                 $body     The canonical JSON bytes to
     *                                           emit verbatim.
     * @param   array<string, string>  $headers  Lowercase header names.
     * @param   bool                   $refusal  True when the body is a
     *                                           host-error document, false
     *                                           for a host-result document.
     *
     * @since   0.1.0
     */
    public function __construct(
        public readonly string $body,
        public readonly array $headers,
        public readonly bool $refusal,
    ) {
    }
}
