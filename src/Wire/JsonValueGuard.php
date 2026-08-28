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

final class JsonValueGuard
{
    public const MAXIMUM_ITEMS = 10000;
    public const MAXIMUM_MEMBERS = 10000;

    private function __construct()
    {
    }

    /**
     * @throws JsonShapeViolation
     */
    public static function assert(mixed $value): void
    {
        self::walk($value, '', 1);
    }

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

    private static function escapePointerSegment(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
