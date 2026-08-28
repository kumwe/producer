<?php

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
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Css;

final class ScopedStylesheet
{
    private const TARGETS = [
        'action' => '[data-studio-part="action"]',
        'content' => '[data-studio-part="content"]',
        'heading' => '[data-studio-part="heading"]',
        'media' => '[data-studio-part="media"]',
        'self' => '',
    ];

    private const ALLOWED_PROPERTIES = [
        'background-color', 'border-color', 'border-radius', 'border-style',
        'border-width', 'color', 'font-family', 'font-size', 'font-style',
        'font-weight', 'gap', 'letter-spacing', 'line-height', 'margin-block',
        'margin-inline', 'max-inline-size', 'min-block-size', 'opacity',
        'padding-block', 'padding-inline', 'text-align', 'text-decoration',
        'text-transform',
    ];

    public const VALUE_PATTERN =
        '/^(?:#[0-9A-Fa-f]{3,8}|-?[0-9]+(?:\.[0-9]+)?(?:ch|em|rem|%|px)?|[a-z][a-z0-9 -]{0,126}|var\(--studio-[a-z0-9-]{1,100}\))$/u';

    public const FORBIDDEN_PATTERN = '/(?:url|expression|javascript|@|[;{}])/iu';

    private function __construct()
    {
    }

    /**
     * Compile structured host style intent into one node-bounded stylesheet.
     *
     * @param object|array{rules?: mixed} $sheet {rules: list of {target,
     *     declarations}} as objects or arrays
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

    private static function member(object|array $record, string $name): mixed
    {
        if (is_array($record)) {
            return $record[$name] ?? null;
        }

        return $record->{$name} ?? null;
    }
}
