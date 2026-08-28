<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * A host-resolved media asset ready for rendering.
 *
 * Mirrors the reference renderer's ResolvedWebMedia shape. The src carried
 * here is whatever the host resolved; the renderer vets it through the URL
 * allowlist before a single byte reaches the page. Immutable — a vetted
 * variant is a new instance via {@see self::withSrc()}.
 *
 * @since   0.1.0
 */
final class ResolvedMedia
{
    /**
     * @param   string   $src        The host-resolved URL, not yet vetted.
     * @param   string   $altText    Alternative text; '' means decorative.
     * @param   ?string  $caption    Optional caption text.
     * @param   ?string  $mediaType  Optional media type, consulted when
     *     vetting blob: URLs.
     * @param   int|float|null  $width   Optional intrinsic width.
     * @param   int|float|null  $height  Optional intrinsic height.
     * @since   0.1.0
     */
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
     * Build from a decoded media descriptor object (assetId/src/altText
     * and the optional caption, mediaType, width, and height members).
     * Never throws: a missing or mistyped member becomes its documented
     * default.
     *
     * @param   \stdClass  $descriptor  The decoded descriptor.
     * @return  self  The coerced media value.
     * @since   0.1.0
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

    /**
     * A copy carrying a replacement src — how the vetted URL supersedes
     * the host-resolved one — with every other member unchanged.
     *
     * @param   string  $src  The replacement URL.
     * @return  self  The new instance.
     * @since   0.1.0
     */
    public function withSrc(string $src): self
    {
        return new self($src, $this->altText, $this->caption, $this->mediaType, $this->width, $this->height);
    }

    /**
     * The width/height attribute fragment the reference emits, with each
     * dimension normalized to a bounded positive integer — a fractional,
     * non-positive, or oversized dimension becomes 1, never the raw value.
     *
     * @return  string  The leading-space attribute fragment; empty when
     *     neither dimension is present.
     * @since   0.1.0
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

    /**
     * Normalize one dimension to an integer from 1 through 100000: a whole
     * number in range passes, everything else becomes 1.
     *
     * @param   int|float  $value  The stored dimension.
     * @return  int  The bounded positive integer.
     * @since   0.1.0
     */
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
