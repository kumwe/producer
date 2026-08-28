<?php

/**
 * A referenced content resource the host resolved for rendering.
 *
 * Mirrors the reference renderer's ResolvedWebResource shape: the URL is
 * vetted through the closed allowlist at parse time, so an unsafe URL simply
 * never exists on the value.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render;

final class ResolvedResource
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly ?string $summary = null,
        public readonly ?string $url = null,
    ) {
    }

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
