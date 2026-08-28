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

/**
 * The typed refusal of {@see JsonValueGuard}: a stable reason code and the
 * JSON Pointer of the offending value, never the value itself.
 *
 * Callers branch on the reason code — {@see RequestEnvelope} turns it into
 * the invalid-argument diagnostic of an invalid-request refusal — and may
 * surface the message safely, because it is fixed prose plus the pointer.
 *
 * @since   0.1.0
 */
final class JsonShapeViolation extends \InvalidArgumentException
{
    /**
     * Binds the violation's stable identity to its human-readable message.
     *
     * @param   string  $reason   Stable code: depth-exceeded, too-many-items,
     *                            too-many-members, unsafe-member-name,
     *                            malformed-text, or unrepresentable.
     * @param   string  $pointer  JSON Pointer to the offending value.
     * @param   string  $message  Fixed prose naming the broken bound plus
     *                            the pointer — never the offending value.
     *
     * @since   0.1.0
     */
    public function __construct(
        private readonly string $reason,
        private readonly string $pointer,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * The stable reason code a caller may match on.
     *
     * @return  string  One of the six codes the guard produces.
     *
     * @since   0.1.0
     */
    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * Where the violation sits inside the guarded value.
     *
     * @return  string  A JSON Pointer, empty for the root value.
     *
     * @since   0.1.0
     */
    public function pointer(): string
    {
        return $this->pointer;
    }
}
