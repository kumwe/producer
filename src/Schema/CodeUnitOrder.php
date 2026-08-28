<?php

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
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Schema;

final class CodeUnitOrder
{
    private function __construct()
    {
    }

    /**
     * Compare two member names by UTF-16 code unit.
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
     * @return list<string>
     */
    public static function sortedMemberNames(\stdClass $value): array
    {
        $names = array_map('strval', array_keys(get_object_vars($value)));
        usort($names, self::compare(...));

        return $names;
    }

    private static function isAscii(string $value): bool
    {
        return preg_match('/[\x80-\xFF]/', $value) !== 1;
    }
}
