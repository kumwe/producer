<?php

declare(strict_types=1);

namespace Kumwe\Producer\Schema;

/**
 * UTF-16 code-unit ordering for the schema profile's deterministic walks.
 *
 * The profile fixes one member order everywhere a JSON object is traversed:
 * ascending UTF-16 code unit, the same comparator canonical serialization
 * sorts by. Comparing the UTF-16BE encodings byte for byte is exactly that
 * order — each code unit is two big-endian bytes, so astral names compare
 * through their surrogate halves — with a plain byte comparison fast path
 * for ASCII-only names.
 *
 * @internal Support type for the Schema namespace; not part of the library
 *           surface.
 *
 * @since   0.1.0
 */
final class CodeUnitOrder
{
    /**
     * Static utility; never instantiated.
     *
     * @since   0.1.0
     */
    private function __construct()
    {
    }

    /**
     * Compare two member names by UTF-16 code unit.
     *
     * @param string $left  UTF-8 member name.
     * @param string $right UTF-8 member name.
     *
     * @return int Negative, zero, or positive as $left sorts before, with,
     *             or after $right.
     *
     * @since   0.1.0
     */
    public static function compare(string $left, string $right): int
    {
        if (self::isAscii($left) && self::isAscii($right)) {
            return strcmp($left, $right);
        }

        return strcmp(
            (string) mb_convert_encoding($left, 'UTF-16BE', 'UTF-8'),
            (string) mb_convert_encoding($right, 'UTF-16BE', 'UTF-8')
        );
    }

    /**
     * List an object's member names as strings, sorted by code unit.
     *
     * @param \stdClass $value Decoded JSON object.
     *
     * @return list<string>
     *
     * @since   0.1.0
     */
    public static function sortedMemberNames(\stdClass $value): array
    {
        $names = array_map('strval', array_keys(get_object_vars($value)));
        usort($names, self::compare(...));

        return $names;
    }

    /**
     * Say whether a string is pure ASCII, where byte order and UTF-16
     * code-unit order already agree.
     *
     * @param string $value UTF-8 text to test.
     *
     * @since   0.1.0
     */
    private static function isAscii(string $value): bool
    {
        return preg_match('/[\x80-\xFF]/', $value) !== 1;
    }
}
