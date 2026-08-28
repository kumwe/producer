<?php

/**
 * Design tokens compiled into CSS custom properties.
 *
 * A theme's design tokens become `--studio-*` custom properties on `:root`,
 * validated against the same closed value grammar as scoped styles and
 * emitted in one deterministic (name-sorted) order. Combined with the static
 * base stylesheet this is the complete stylesheet a design implies — built
 * once when a theme is published, never per request.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Css;

final class ThemeStylesheet
{
    private const NAME_PATTERN = '/^[a-z][a-z0-9-]{0,100}$/';

    private function __construct()
    {
    }

    /**
     * Compile a token map (name => value) into a `:root` custom-property
     * block. Token names are used without the `--studio-` prefix, which the
     * compiler adds; values must satisfy the closed scoped-CSS value grammar.
     *
     * @param array<string, string> $tokens
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
     * The complete static stylesheet for a design: its custom properties
     * followed by the semantic-web base (including the reduced-motion
     * overrides).
     *
     * @param array<string, string> $tokens
     */
    public static function document(array $tokens = []): string
    {
        $compiled = self::compile($tokens);

        return $compiled === '' ? BaseStylesheet::css() : $compiled . "\n" . BaseStylesheet::css();
    }
}
