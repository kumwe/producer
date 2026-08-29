<?php

declare(strict_types=1);

namespace Kumwe\Producer\Css;

/**
 * Node-scoped structured style compilation.
 *
 * Ported from the reference renderer's scoped-css vocabulary: a host
 * expresses per-node style intent as data (target part plus declarations
 * from a closed property allowlist and a closed value grammar), and this
 * compiler turns it into one bounded stylesheet under the node's scope
 * attribute. Selectors, URLs, at-rules, and free-form CSS have no
 * representation — the input is structure, never stylesheet text.
 *
 * @since   0.1.0
 */
final class ScopedStylesheet
{
    /**
     * The closed target vocabulary: rule target name to the part selector
     * appended to the node's scope selector. `self` styles the scoped
     * element itself; every other target styles exactly one named part
     * inside it. A target outside this map is refused.
     *
     * @since   0.1.0
     */
    private const TARGETS = [
        'action' => '[data-studio-part="action"]',
        'content' => '[data-studio-part="content"]',
        'heading' => '[data-studio-part="heading"]',
        'media' => '[data-studio-part="media"]',
        'self' => '',
    ];

    /**
     * The complete CSS property allowlist. A declaration naming any
     * property outside this list is refused; positioning, sizing tricks,
     * resource-loading, and behavioral properties have no representation.
     *
     * @since   0.1.0
     */
    private const ALLOWED_PROPERTIES = [
        'background-color', 'border-color', 'border-radius', 'border-style',
        'border-width', 'color', 'font-family', 'font-size', 'font-style',
        'font-weight', 'gap', 'letter-spacing', 'line-height', 'margin-block',
        'margin-inline', 'max-inline-size', 'min-block-size', 'opacity',
        'padding-block', 'padding-inline', 'text-align', 'text-decoration',
        'text-transform',
    ];

    /**
     * The closed value grammar every declaration value must match in full:
     * a hex color, a signed decimal number with an optional approved unit
     * (ch, em, rem, %, px), a bounded lowercase keyword sequence, or a
     * `var(--studio-*)` token reference. No url(), no calc(), no quotes,
     * no escapes — nothing else has a representation.
     *
     * @since   0.1.0
     */
    public const VALUE_PATTERN =
        '/^(?:#[0-9A-Fa-f]{3,8}|-?[0-9]+(?:\.[0-9]+)?(?:ch|em|rem|%|px)?|[a-z][a-z0-9 -]{0,126}|var\(--studio-[a-z0-9-]{1,100}\))$/u';

    /**
     * Defense-in-depth refusal net applied after {@see self::VALUE_PATTERN}:
     * a value containing url, expression, javascript, an at-sign, or CSS
     * structural punctuation is refused even when the grammar matched.
     *
     * @since   0.1.0
     */
    public const FORBIDDEN_PATTERN = '/(?:url|expression|javascript|@|[;{}])/iu';

    /**
     * Static compiler; never instantiated.
     *
     * @since   0.1.0
     */
    private function __construct()
    {
    }

    /**
     * Compile structured host style intent into one node-bounded stylesheet.
     *
     * Accepts at most 100 rules of at most 50 declarations each; every
     * property must come from the closed allowlist and every value — a
     * string of at most 256 bytes — must satisfy the closed value grammar
     * and pass the forbidden-token net. Each rule's declarations are
     * emitted sorted by property name, so identical input compiles to
     * identical bytes. Every emitted selector is prefixed with the node's
     * scope attribute selector; no rule can reach outside the node.
     *
     * @param   string  $scope  CSS-safe scope token (a letter, then at most
     *     511 further letters, digits, underscores, or dashes), as produced
     *     by {@see \Kumwe\Producer\Render\CompositionRenderer::scopeFor()}.
     * @param   object|array{rules?: mixed}  $sheet  {rules: list of {target,
     *     declarations}} as objects or arrays.
     * @return  string  The compiled stylesheet text; empty for zero rules.
     * @throws  CssException  On an unsafe scope token, a missing or
     *     oversized rules list, an unknown target or property, or a value
     *     outside the closed grammar.
     * @since   0.1.0
     */
    public static function compile(string $scope, object|array $sheet): string
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,511}$/u', $scope) !== 1) {
            throw new CssException('Scoped CSS scope must be a bounded CSS-safe identifier.');
        }
        $rules = self::member($sheet, 'rules');
        if (!is_array($rules)) {
            throw new CssException('Scoped stylesheet requires a rules list.');
        }
        if (count($rules) > 100) {
            throw new CssException('Scoped stylesheet exceeds 100 rules.');
        }
        $base = '[data-studio-scope="' . $scope . '"]';
        $compiled = '';
        foreach ($rules as $rule) {
            if (!is_object($rule) && !is_array($rule)) {
                throw new CssException('Scoped style rules must be structured objects.');
            }
            $target = self::member($rule, 'target');
            if (!is_string($target) || !array_key_exists($target, self::TARGETS)) {
                throw new CssException('Scoped CSS target ' . (is_string($target) ? $target : get_debug_type($target)) . ' is not allowed.');
            }
            $declarations = self::member($rule, 'declarations');
            if ($declarations instanceof \stdClass) {
                $declarations = get_object_vars($declarations);
            }
            if (!is_array($declarations)) {
                throw new CssException('Scoped style rules require a declarations map.');
            }
            if (count($declarations) > 50) {
                throw new CssException('Scoped style rule exceeds 50 declarations.');
            }
            ksort($declarations, SORT_STRING);
            $parts = [];
            foreach ($declarations as $property => $value) {
                $property = (string) $property;
                if (!in_array($property, self::ALLOWED_PROPERTIES, true)) {
                    throw new CssException("Scoped CSS property {$property} is not allowed.");
                }
                if (
                    !is_string($value)
                    || strlen($value) > 256
                    || preg_match(self::VALUE_PATTERN, $value) !== 1
                    || preg_match(self::FORBIDDEN_PATTERN, $value) === 1
                ) {
                    throw new CssException("Scoped CSS value for {$property} is not allowed.");
                }
                $parts[] = $property . ':' . $value;
            }
            $compiled .= $base . self::TARGETS[$target] . '{' . implode(';', $parts) . '}';
        }

        return $compiled;
    }

    /**
     * Read one member from a record decoded as either an object or an
     * array, treating the two shapes identically.
     *
     * @param   object|array<array-key, mixed>  $record  The decoded sheet or rule record.
     * @param   string                          $name    The member name to read.
     * @return  mixed  The member value, or null when the member is absent.
     * @since   0.1.0
     */
    private static function member(object|array $record, string $name): mixed
    {
        if (is_array($record)) {
            return $record[$name] ?? null;
        }

        return $record->{$name} ?? null;
    }
}
