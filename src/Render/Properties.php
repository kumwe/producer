<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * Bounded property and binding coercion helpers.
 *
 * Ports the reference renderer's integerProperty, stringProperty,
 * toneProperty, and stringValue helpers: every stored value is coerced into
 * a closed vocabulary or replaced by its documented fallback, never trusted.
 * Nothing here throws — a value outside its bound silently becomes the
 * fallback, which is what keeps content problems from breaking a page.
 *
 * @since   0.1.0
 */
final class Properties
{
    /**
     * Static helper set; never instantiated.
     *
     * @since   0.1.0
     */
    private function __construct()
    {
    }

    /**
     * One stored property of a node, exactly as stored.
     *
     * @param   \stdClass  $node  The decoded Blueprint node.
     * @param   string     $name  The property name.
     * @return  mixed  The stored value, or null when absent.
     * @since   0.1.0
     */
    public static function property(\stdClass $node, string $name): mixed
    {
        return $node->properties->{$name} ?? null;
    }

    /**
     * Coerce a stored value to an in-range integer, accepting a whole
     * float as the integer it denotes; anything else — out of range,
     * fractional, non-numeric — yields the fallback.
     *
     * @param   mixed  $value     The stored value.
     * @param   int    $minimum   Least accepted value, inclusive.
     * @param   int    $maximum   Greatest accepted value, inclusive.
     * @param   int    $fallback  The documented fallback.
     * @return  int  The accepted integer or the fallback.
     * @since   0.1.0
     */
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

    /**
     * A stored string exactly as stored, or the fallback for any
     * non-string; the empty string is a valid stored value here.
     *
     * @param   mixed   $value     The stored value.
     * @param   string  $fallback  The documented fallback.
     * @return  string  The stored string or the fallback.
     * @since   0.1.0
     */
    public static function stringProperty(mixed $value, string $fallback): string
    {
        return is_string($value) ? $value : $fallback;
    }

    /**
     * A stored string admitted only when it is one of the allowed values;
     * anything else yields the fallback, never the stored bytes.
     *
     * @param   mixed         $value     The stored value.
     * @param   list<string>  $allowed   The closed vocabulary.
     * @param   string        $fallback  The documented fallback.
     * @return  string  A member of the vocabulary or the fallback.
     * @since   0.1.0
     */
    public static function enumProperty(mixed $value, array $allowed, string $fallback): string
    {
        $candidate = self::stringProperty($value, '');

        return in_array($candidate, $allowed, true) ? $candidate : $fallback;
    }

    /**
     * The closed tone vocabulary shared by toned blocks: error,
     * information, neutral, success, or warning — anything else is
     * neutral.
     *
     * @param   mixed  $value  The stored value.
     * @return  string  A member of the tone vocabulary.
     * @since   0.1.0
     */
    public static function toneProperty(mixed $value): string
    {
        return self::enumProperty($value, ['error', 'information', 'neutral', 'success', 'warning'], 'neutral');
    }

    /**
     * A stored string exactly as stored, or the empty string for any
     * non-string — the reference renderer's stringValue helper.
     *
     * @param   mixed  $value  The stored value.
     * @return  string  The stored string or ''.
     * @since   0.1.0
     */
    public static function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * The reference renderer's `stringValue(...) || fallback` idiom: an
     * ECMAScript-falsy string ('' and '0') yields the fallback, mirroring
     * the reference byte for byte.
     *
     * @param   mixed   $value     The stored value.
     * @param   string  $fallback  The documented fallback.
     * @return  string  The truthy stored string or the fallback.
     * @since   0.1.0
     */
    public static function stringValueOr(mixed $value, string $fallback): string
    {
        $string = self::stringValue($value);

        return $string === '' || $string === '0' ? $fallback : $string;
    }
}
