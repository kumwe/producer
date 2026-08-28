<?php

declare(strict_types=1);

namespace Kumwe\Producer\Canonical;

/**
 * Refusal raised while producing canonical JSON.
 *
 * Carries the stable rejection code the canonical conformance vectors name,
 * so a caller maps refusals without parsing prose.
 *
 * @since   0.1.0
 */
final class CanonicalEncodingException extends \RuntimeException
{
    /**
     * Hold the refusal's stable code alongside its human-readable message.
     *
     * @param string $rejection Stable code: depth-exceeded, forbidden-member,
     *                          or unrepresentable.
     * @param string $message   Human diagnostic; never used for matching.
     *
     * @since   0.1.0
     */
    public function __construct(private readonly string $rejection, string $message)
    {
        parent::__construct($message);
    }

    /**
     * The stable rejection code: `depth-exceeded` when nesting passes the
     * serialization bound, `forbidden-member` for a prototype-polluting
     * object member name, or `unrepresentable` for a value canonical JSON
     * cannot encode.
     *
     * @since   0.1.0
     */
    public function rejection(): string
    {
        return $this->rejection;
    }
}
