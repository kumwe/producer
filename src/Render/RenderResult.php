<?php

/**
 * The immutable outcome of rendering a composition.
 *
 * Carries the semantic HTML, the static stylesheet text, and the list of
 * requested enhancements. Unlike the reference renderer this result carries
 * no prebuilt style element: the charter forbids Producer to emit inline
 * script or style elements, so the host embeds the CSS through its own
 * pipeline (a file, a nonce'd element it owns, an HTTP-pushed asset).
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render;

final class RenderResult
{
    /**
     * @param list<Enhancement> $enhancements every enhancement request in
     *     document order, exactly as the reference renderer records them
     */
    public function __construct(
        public readonly string $html,
        public readonly string $css,
        public readonly array $enhancements,
    ) {
    }

    /**
     * The unique requested enhancement names, deduplicated while preserving
     * request (document) order — the ordering the conformance corpus fixes.
     *
     * @return list<string>
     */
    public function enhancementNames(): array
    {
        $names = [];
        foreach ($this->enhancements as $enhancement) {
            if (!in_array($enhancement->kind, $names, true)) {
                $names[] = $enhancement->kind;
            }
        }

        return $names;
    }
}
