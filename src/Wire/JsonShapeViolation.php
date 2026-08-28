<?php

/**
 * Refusal raised while proving a value fits the contract's jsonValue shape.
 *
 * Carries a stable reason code and the JSON Pointer of the offending
 * value; the message is fixed prose plus that pointer, never the value
 * itself, so a caller can surface the violation without disclosure.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire;

final class JsonShapeViolation extends \InvalidArgumentException
{
    /**
     * @param string $reason  Stable code: depth-exceeded, too-many-items,
     *                        too-many-members, unsafe-member-name,
     *                        malformed-text, or unrepresentable.
     * @param string $pointer JSON Pointer to the offending value.
     */
    public function __construct(
        private readonly string $reason,
        private readonly string $pointer,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function pointer(): string
    {
        return $this->pointer;
    }
}
