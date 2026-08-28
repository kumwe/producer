<?php

/**
 * Refusal raised while producing canonical JSON.
 *
 * Carries the stable rejection code the canonical conformance vectors name,
 * so a caller maps refusals without parsing prose.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Canonical;

final class CanonicalEncodingException extends \RuntimeException
{
    /**
     * @param string $rejection Stable code: depth-exceeded, forbidden-member,
     *                          or unrepresentable.
     * @param string $message   Human diagnostic; never used for matching.
     */
    public function __construct(private readonly string $rejection, string $message)
    {
        parent::__construct($message);
    }

    public function rejection(): string
    {
        return $this->rejection;
    }
}
