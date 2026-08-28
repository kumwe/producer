<?php

/**
 * Proof that a decoded value fits common.schema.json#/$defs/jsonValue.
 *
 * The schema bounds arrays at 10000 items and objects at 10000 members
 * with safe member names; this guard adds the canonical serialization's
 * depth bound so every accepted value is guaranteed to canonicalize. The
 * depth count starts at 1 because a guarded value always travels inside
 * one wrapping document member (`arguments` or `value`).
 *
 * Values follow the decoded-JSON shape: objects are stdClass, arrays are
 * lists. An associative PHP array is refused as unrepresentable rather
 * than guessed at, exactly as the canonical serializer refuses it.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Error\ContractGrammar;

/**
 * The single gate deciding whether a decoded value is a contract
 * jsonValue: bounded, safe-named, finite, valid UTF-8, and shallow enough
 * to canonicalize.
 *
 * Both wire directions pass through it — request arguments in
 * {@see RequestEnvelope} and result values in {@see HostResult} — so a
 * value that circulates has already been proven, before any expensive
 * work, unable to amplify sorting, hashing, or serialization cost.
 *
 * @since   0.1.0
 */
final class JsonValueGuard
{
    /**
     * The schema's bound on array length: at most 10000 items.
     *
     * @since   0.1.0
     */
    public const MAXIMUM_ITEMS = 10000;

    /**
     * The schema's bound on object size: at most 10000 members.
     *
     * @since   0.1.0
     */
    public const MAXIMUM_MEMBERS = 10000;

    /**
     * Not constructable: the guard is a stateless pair of static checks.
     *
     * @since   0.1.0
     */
    private function __construct()
    {
    }

    /**
     * Proves one decoded value fits the contract jsonValue shape; returns
     * silently on success. Depth counting starts at 1 because the value
     * always travels inside one wrapping document member.
     *
     * @param   mixed  $value  The decoded value: null, bool, int, finite
     *                         float, UTF-8 string, list array, or stdClass.
     *
     * @throws  JsonShapeViolation  Naming the broken bound and the JSON
     *                              Pointer of the offending value.
     *
     * @since   0.1.0
     */
    public static function assert(mixed $value): void
    {
        self::walk($value, '', 1);
    }

    /**
     * Recursive worker: checks the value at one pointer, cheapest test
     * first, and recurses into arrays and objects with depth tracked
     * against the canonical bound.
     *
     * @param   mixed   $value    The value under test.
     * @param   string  $pointer  The JSON Pointer of that value, empty at
     *                            the root.
     * @param   int     $depth    The nesting level, 1 for the root.
     *
     * @throws  JsonShapeViolation  On the first violated bound.
     *
     * @since   0.1.0
     */
    private static function walk(mixed $value, string $pointer, int $depth): void
    {
        if ($value === null || is_bool($value) || is_int($value)) {
            return;
        }
        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new JsonShapeViolation(
                    'unrepresentable',
                    $pointer,
                    "A contract JSON number must be finite at {$pointer}."
                );
            }

            return;
        }
        if (is_string($value)) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                throw new JsonShapeViolation(
                    'malformed-text',
                    $pointer,
                    "A contract JSON string must be valid UTF-8 at {$pointer}."
                );
            }

            return;
        }

        if ($depth >= CanonicalJson::DEFAULT_MAXIMUM_DEPTH) {
            throw new JsonShapeViolation(
                'depth-exceeded',
                $pointer,
                "A contract JSON value exceeds the canonical depth bound at {$pointer}."
            );
        }

        if (is_array($value)) {
            if (!array_is_list($value)) {
                throw new JsonShapeViolation(
                    'unrepresentable',
                    $pointer,
                    "A contract JSON array must be a list at {$pointer}; decode objects as stdClass."
                );
            }
            if (count($value) > self::MAXIMUM_ITEMS) {
                throw new JsonShapeViolation(
                    'too-many-items',
                    $pointer,
                    "A contract JSON array holds at most 10000 items at {$pointer}."
                );
            }
            foreach ($value as $index => $item) {
                self::walk($item, $pointer . '/' . $index, $depth + 1);
            }

            return;
        }

        if ($value instanceof \stdClass) {
            $members = get_object_vars($value);
            if (count($members) > self::MAXIMUM_MEMBERS) {
                throw new JsonShapeViolation(
                    'too-many-members',
                    $pointer,
                    "A contract JSON object holds at most 10000 members at {$pointer}."
                );
            }
            foreach ($members as $name => $member) {
                $name = (string) $name;
                if (!ContractGrammar::isSafeJsonMemberName($name)) {
                    throw new JsonShapeViolation(
                        'unsafe-member-name',
                        $pointer,
                        "A contract JSON object member name is unsafe at {$pointer}."
                    );
                }
                self::walk($member, $pointer . '/' . self::escapePointerSegment($name), $depth + 1);
            }

            return;
        }

        throw new JsonShapeViolation(
            'unrepresentable',
            $pointer,
            'A contract JSON value cannot be a ' . get_debug_type($value) . " at {$pointer}."
        );
    }

    /**
     * RFC 6901 escaping for one pointer segment: `~` becomes `~0`, `/`
     * becomes `~1`.
     *
     * @param   string  $segment  The raw member name.
     *
     * @return  string  The segment safe to join with `/`.
     *
     * @since   0.1.0
     */
    private static function escapePointerSegment(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
