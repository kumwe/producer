<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * A referenced content resource the host resolved for rendering.
 *
 * Mirrors the reference renderer's ResolvedWebResource shape: the URL is
 * vetted through the closed allowlist at parse time, so an unsafe URL simply
 * never exists on the value. Immutable once constructed.
 *
 * @since   0.1.0
 */
final class ResolvedResource
{
    /**
     * @param   string   $id       The resource's stable identifier.
     * @param   string   $label    The human-readable label to render.
     * @param   ?string  $summary  Optional summary text.
     * @param   ?string  $url      An already-vetted URL, or null when the
     *     resource has no safe link.
     * @since   0.1.0
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly ?string $summary = null,
        public readonly ?string $url = null,
    ) {
    }

    /**
     * Parse a bound resource value. Requires a decoded object with string
     * id and label — anything else yields null, never an error. The url
     * member is vetted through {@see SafeMarkup::safeUrl()} here, so a
     * refused URL becomes null on the parsed value.
     *
     * @param   mixed  $value  The bound value.
     * @return  ?self  The parsed resource, or null when unusable.
     * @since   0.1.0
     */
    public static function parse(mixed $value): ?self
    {
        if (
            !$value instanceof \stdClass
            || !is_string($value->id ?? null)
            || !is_string($value->label ?? null)
        ) {
            return null;
        }
        $summary = is_string($value->summary ?? null) ? $value->summary : null;
        $url = is_string($value->url ?? null) ? SafeMarkup::safeUrl($value->url) : null;

        return new self($value->id, $value->label, $summary, $url);
    }
}
