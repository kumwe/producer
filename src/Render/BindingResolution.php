<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * Closed outcome of resolving one Blueprint binding.
 *
 * Available null, unavailable, and hidden are distinct. A hidden outcome
 * never exposes a sentinel as block data; the renderer marks the owning
 * wrapper hidden after the block has safely rendered its fallback.
 *
 * @since   0.2.0
 */
final class BindingResolution
{
    /**
     * A trusted value exists, including an explicit null.
     *
     * @since   0.2.0
     */
    private const AVAILABLE = 'available';

    /**
     * Binding policy suppresses the owning node.
     *
     * @since   0.2.0
     */
    private const HIDDEN = 'hidden';

    /**
     * No trusted value is available and the node remains visible.
     *
     * @since   0.2.0
     */
    private const UNAVAILABLE = 'unavailable';

    /**
     * @param   string  $state  One closed resolution state.
     * @param   mixed   $value  Available value, null otherwise.
     *
     * @since   0.2.0
     */
    private function __construct(
        private readonly string $state,
        private readonly mixed $value,
    ) {
    }

    /**
     * Capture a trusted resolved value, including an explicit null.
     *
     * @param   mixed  $value  Resolved canonical value.
     *
     * @return  self  Available resolution.
     *
     * @since   0.2.0
     */
    public static function available(mixed $value): self
    {
        return new self(self::AVAILABLE, $value);
    }

    /**
     * Suppress the owning block under binding policy.
     *
     * @return  self  Hidden resolution carrying no value.
     *
     * @since   0.2.0
     */
    public static function hidden(): self
    {
        return new self(self::HIDDEN, null);
    }

    /**
     * Report that no trusted value is available.
     *
     * @return  self  Unavailable non-hidden resolution.
     *
     * @since   0.2.0
     */
    public static function unavailable(): self
    {
        return new self(self::UNAVAILABLE, null);
    }

    /**
     * Whether a trusted value is available.
     *
     * @return  bool  True for available, including available null.
     *
     * @since   0.2.0
     */
    public function isAvailable(): bool
    {
        return $this->state === self::AVAILABLE;
    }

    /**
     * Whether binding policy suppresses the owning block.
     *
     * @return  bool  True only for hidden.
     *
     * @since   0.2.0
     */
    public function isHidden(): bool
    {
        return $this->state === self::HIDDEN;
    }

    /**
     * The available value, or null when hidden/unavailable.
     *
     * Use {@see isAvailable()} when explicit null must be distinguished.
     *
     * @return  mixed  Resolved value or null.
     *
     * @since   0.2.0
     */
    public function value(): mixed
    {
        return $this->value;
    }
}
