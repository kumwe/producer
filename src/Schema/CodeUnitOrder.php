<?php

declare(strict_types=1);

namespace Kumwe\Producer\Schema;

use Kumwe\Producer\Canonical\CanonicalJson;

/**
 * UTF-16 code-unit ordering for the schema profile's deterministic walks.
 *
 * The profile fixes one member order everywhere a JSON object is traversed:
 * ascending UTF-16 code unit, the same comparator canonical serialization
 * sorts by. The canonical serializer owns the comparator so schema walks
 * and host code use one public implementation.
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
        return CanonicalJson::compareCodeUnits($left, $right);
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
}
