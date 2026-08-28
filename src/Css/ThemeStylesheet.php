<?php

declare(strict_types=1);

namespace Kumwe\Producer\Css;

/**
 * Design tokens compiled into CSS custom properties.
 *
 * A theme's design tokens become `--studio-*` custom properties on `:root`,
 * validated against the same closed value grammar as scoped styles and
 * emitted in one deterministic (name-sorted) order. Combined with the static
 * base stylesheet this is the complete stylesheet a design implies — built
 * once when a theme is published, never per request.
 *
 * @since   0.1.0
 */
final class ThemeStylesheet
{
    /**
     * The closed token-name grammar: a lowercase letter followed by at most
     * 100 further lowercase letters, digits, or dashes. A name outside it
     * is refused before any value is inspected.
     *
     * @since   0.1.0
     */
    private const NAME_PATTERN = '/^[a-z][a-z0-9-]{0,100}$/';

    /**
     * Static compiler; never instantiated.
     *
     * @since   0.1.0
     */
    private function __construct()
    {
    }

    /**
     * Compile a token map (name => value) into a `:root` custom-property
     * block. Token names are given without the `--studio-` prefix, which
     * the compiler adds; each value — a string of at most 256 bytes — must
     * satisfy the closed scoped-CSS value grammar and pass the
     * forbidden-token net. At most 1000 tokens are accepted, and the
     * declarations are emitted sorted by token name, so an identical map
     * compiles to identical bytes.
     *
     * @param   array<string, string>  $tokens  Design tokens, name to value.
     * @return  string  The `:root` block; empty for an empty token map.
     * @throws  CssException  On an oversized map, a token name outside the
     *     closed grammar, or a value outside the closed value grammar.
     * @since   0.1.0
     */
    public static function compile(array $tokens): string
    {
        if (count($tokens) > 1000) {
            throw new CssException('Theme token map exceeds 1000 tokens.');
        }
        ksort($tokens, SORT_STRING);
        $declarations = [];
        foreach ($tokens as $name => $value) {
            $name = (string) $name;
            if (preg_match(self::NAME_PATTERN, $name) !== 1) {
                throw new CssException("Theme token name {$name} is not a bounded lowercase identifier.");
            }
            if (
                !is_string($value)
                || strlen($value) > 256
                || preg_match(ScopedStylesheet::VALUE_PATTERN, $value) !== 1
                || preg_match(ScopedStylesheet::FORBIDDEN_PATTERN, $value) === 1
            ) {
                throw new CssException("Theme token value for {$name} is not allowed.");
            }
            $declarations[] = '--studio-' . $name . ':' . $value;
        }

        return $declarations === [] ? '' : ':root{' . implode(';', $declarations) . '}';
    }

    /**
     * The complete static stylesheet for a design: its compiled custom
     * properties followed by the semantic-web base stylesheet (including
     * the reduced-motion overrides), joined by one newline. With no tokens
     * the result is the base stylesheet alone. Deterministic: identical
     * tokens yield identical bytes.
     *
     * @param   array<string, string>  $tokens  Design tokens, name to value.
     * @return  string  The full stylesheet a host serves for the design.
     * @throws  CssException  When the token map is refused by
     *     {@see self::compile()}.
     * @since   0.1.0
     */
    public static function document(array $tokens = []): string
    {
        $compiled = self::compile($tokens);

        return $compiled === '' ? BaseStylesheet::css() : $compiled . "\n" . BaseStylesheet::css();
    }
}
