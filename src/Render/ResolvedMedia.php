<?php

/**
 * A host-resolved media asset ready for rendering.
 *
 * Mirrors the reference renderer's ResolvedWebMedia shape. The src carried
 * here is whatever the host resolved; the renderer vets it through the URL
 * allowlist before a single byte reaches the page.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render;

final class ResolvedMedia
{
    public function __construct(
        public readonly string $src,
        public readonly string $altText = '',
        public readonly ?string $caption = null,
        public readonly ?string $mediaType = null,
        public readonly int|float|null $width = null,
        public readonly int|float|null $height = null,
    ) {
    }

    /**
     * Build from a decoded media descriptor object (assetId/src/altText and
     * the optional caption, mediaType, width, and height members).
     */
    public static function fromDescriptor(\stdClass $descriptor): self
    {
        return new self(
            src: Properties::stringValue($descriptor->src ?? null),
            altText: Properties::stringValue($descriptor->altText ?? null),
            caption: is_string($descriptor->caption ?? null) ? $descriptor->caption : null,
            mediaType: is_string($descriptor->mediaType ?? null) ? $descriptor->mediaType : null,
            width: is_int($descriptor->width ?? null) || is_float($descriptor->width ?? null) ? $descriptor->width : null,
            height: is_int($descriptor->height ?? null) || is_float($descriptor->height ?? null) ? $descriptor->height : null,
        );
    }

    public function withSrc(string $src): self
    {
        return new self($src, $this->altText, $this->caption, $this->mediaType, $this->width, $this->height);
    }

    /**
     * The width/height attribute fragment the reference emits, with each
     * dimension normalized to a bounded positive integer.
     */
    public function dimensionsAttribute(): string
    {
        $out = '';
        if ($this->width !== null) {
            $out .= ' width="' . self::positiveInteger($this->width) . '"';
        }
        if ($this->height !== null) {
            $out .= ' height="' . self::positiveInteger($this->height) . '"';
        }

        return $out;
    }

    private static function positiveInteger(int|float $value): int
    {
        if (is_float($value)) {
            if (floor($value) !== $value) {
                return 1;
            }
            $value = (int) $value;
        }

        return $value > 0 && $value <= 100000 ? $value : 1;
    }
}
