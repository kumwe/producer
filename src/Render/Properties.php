<?php

/**
 * Bounded property and binding coercion helpers.
 *
 * Ports the reference renderer's integerProperty, stringProperty,
 * toneProperty, and stringValue helpers: every stored value is coerced into
 * a closed vocabulary or replaced by its documented fallback, never trusted.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render;

final class Properties
{
    private function __construct()
    {
    }

    public static function property(\stdClass $node, string $name): mixed
    {
        return $node->properties->{$name} ?? null;
    }

    public static function integerProperty(mixed $value, int $minimum, int $maximum, int $fallback): int
    {
        if (is_int($value) && $value >= $minimum && $value <= $maximum) {
            return $value;
        }
        if (is_float($value) && floor($value) === $value && $value >= (float) $minimum && $value <= (float) $maximum) {
            return (int) $value;
        }

        return $fallback;
    }

    public static function stringProperty(mixed $value, string $fallback): string
    {
        return is_string($value) ? $value : $fallback;
    }

    /**
     * @param list<string> $allowed
     */
    public static function enumProperty(mixed $value, array $allowed, string $fallback): string
    {
        $candidate = self::stringProperty($value, '');

        return in_array($candidate, $allowed, true) ? $candidate : $fallback;
    }

    public static function toneProperty(mixed $value): string
    {
        return self::enumProperty($value, ['error', 'information', 'neutral', 'success', 'warning'], 'neutral');
    }

    public static function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * The reference renderer's `stringValue(...) || fallback` idiom: an
     * ECMAScript-falsy string ('' and '0') yields the fallback, mirroring
     * the reference byte for byte.
     */
    public static function stringValueOr(mixed $value, string $fallback): string
    {
        $string = self::stringValue($value);

        return $string === '' || $string === '0' ? $fallback : $string;
    }
}
