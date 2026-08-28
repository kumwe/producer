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

final class Response
{
    /**
     * @param array<string, string> $headers Lowercase header names.
     */
    public function __construct(
        public readonly string $body,
        public readonly array $headers,
        public readonly bool $refusal,
    ) {
    }
}
