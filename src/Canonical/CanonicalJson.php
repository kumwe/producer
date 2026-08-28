<?php

/**
 * Canonical cross-language JSON, byte-identical to Studio's serialization.
 *
 * The portability contract fixes one canonical form for checksums: UTF-8
 * JSON with object members sorted by UTF-16 code unit, arrays in semantic
 * order, minimal ECMA-404 string escaping, deterministic numbers with
 * negative zero canonicalized, a bounded depth, and the prototype-polluting
 * member names refused. Studio's canonical conformance vectors prove this
 * implementation produces the same bytes as every other conforming runtime.
 *
 * Values follow PHP's decoded-JSON shape: objects are stdClass and arrays
 * are lists, which is what keeps {} and [] distinguishable. Decode inputs
 * with {@see CanonicalJson::decode()} to preserve that distinction.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Canonical;

final class CanonicalJson
{
    public const DEFAULT_MAXIMUM_DEPTH = 64;

    private const FORBIDDEN_MEMBERS = ['__proto__', 'prototype', 'constructor'];

    private function __construct()
    {
    }

    /**
     * Decode JSON text into the canonical value shape (stdClass objects,
     * list arrays), refusing malformed input.
     */
    public static function decode(string $json): mixed
    {
        return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * The canonical string form of a value.
     */
    public static function stringify(mixed $value, int $maximumDepth = self::DEFAULT_MAXIMUM_DEPTH): string
    {
        if ($maximumDepth < 1) {
            throw new CanonicalEncodingException(
                'unrepresentable',
                'Canonical serialization depth must be a positive integer.'
            );
        }

        return self::serialize($value, $maximumDepth, 0);
    }

    /**
     * The SRI-style digest the contracts compute over the canonical bytes.
     */
    public static function digest(mixed $value, int $maximumDepth = self::DEFAULT_MAXIMUM_DEPTH): string
    {
        return 'sha256-' . base64_encode(hash('sha256', self::stringify($value, $maximumDepth), true));
    }

    private static function serialize(mixed $value, int $maximumDepth, int $depth): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return self::number($value);
        }
        if (is_string($value)) {
            return self::quote($value);
        }

        if ($depth >= $maximumDepth) {
            throw new CanonicalEncodingException(
                'depth-exceeded',
                "Canonical serialization exceeds the depth limit of {$maximumDepth}."
            );
        }

        if (is_array($value)) {
            if (!array_is_list($value)) {
                throw new CanonicalEncodingException(
                    'unrepresentable',
                    'Canonical JSON arrays must be lists; decode objects as stdClass.'
                );
            }
            $items = [];
            foreach ($value as $item) {
                $items[] = self::serialize($item, $maximumDepth, $depth + 1);
            }

            return '[' . implode(',', $items) . ']';
        }

        if ($value instanceof \stdClass) {
            $members = array_map('strval', array_keys(get_object_vars($value)));
            usort($members, self::compareCodeUnits(...));
            $parts = [];
            foreach ($members as $member) {
                if (in_array($member, self::FORBIDDEN_MEMBERS, true)) {
                    throw new CanonicalEncodingException(
                        'forbidden-member',
                        "Canonical JSON forbids the object member name {$member}."
                    );
                }
                $parts[] = self::quote($member) . ':'
                    . self::serialize($value->{$member}, $maximumDepth, $depth + 1);
            }

            return '{' . implode(',', $parts) . '}';
        }

        throw new CanonicalEncodingException(
            'unrepresentable',
            'Canonical JSON cannot represent a ' . get_debug_type($value) . ' value.'
        );
    }

    /**
     * Deterministic number grammar matching the reference: negative zero
     * canonicalizes to 0, integer-valued doubles print as integers, and
     * non-finite values are refused.
     */
    private static function number(float $value): string
    {
        if (!is_finite($value)) {
            throw new CanonicalEncodingException(
                'unrepresentable',
                'Canonical JSON cannot represent a non-finite number.'
            );
        }
        if ($value === 0.0) {
            return '0';
        }
        if (floor($value) === $value && abs($value) < 9007199254740992.0) {
            return sprintf('%.0F', $value);
        }
        $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);

        return $encoded;
    }

    /**
     * ECMA-404 minimal escaping: quote, backslash, the five short control
     * escapes, four lowercase hex digits for the remaining C0 range, and
     * every other code point emitted as raw UTF-8.
     */
    private static function quote(string $value): string
    {
        $out = '"';
        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            $byte = $value[$index];
            $code = ord($byte);
            if ($byte === '"') {
                $out .= '\\"';
            } elseif ($byte === '\\') {
                $out .= '\\\\';
            } elseif ($code >= 0x20) {
                $out .= $byte;
            } else {
                $out .= match ($code) {
                    0x08 => '\\b',
                    0x09 => '\\t',
                    0x0a => '\\n',
                    0x0c => '\\f',
                    0x0d => '\\r',
                    default => sprintf('\\u%04x', $code),
                };
            }
        }

        return $out . '"';
    }

    /**
     * Member ordering by UTF-16 code unit, the reference comparator's
     * semantics: code points at or above U+10000 compare through their
     * surrogate halves, so astral names order exactly as they do in the
     * TypeScript implementation.
     */
    private static function compareCodeUnits(string $left, string $right): int
    {
        $a = self::utf16CodeUnits($left);
        $b = self::utf16CodeUnits($right);
        $length = min(count($a), count($b));
        for ($index = 0; $index < $length; $index++) {
            if ($a[$index] !== $b[$index]) {
                return $a[$index] <=> $b[$index];
            }
        }

        return count($a) <=> count($b);
    }

    /**
     * @return list<int>
     */
    private static function utf16CodeUnits(string $value): array
    {
        $units = [];
        $offset = 0;
        $length = strlen($value);
        while ($offset < $length) {
            $code = mb_ord(mb_substr(substr($value, $offset), 0, 1, 'UTF-8'), 'UTF-8');
            if ($code === false) {
                $units[] = 0xfffd;
                $offset++;
                continue;
            }
            $bytes = strlen(mb_chr($code, 'UTF-8'));
            $offset += max(1, $bytes);
            if ($code >= 0x10000) {
                $adjusted = $code - 0x10000;
                $units[] = 0xd800 + ($adjusted >> 10);
                $units[] = 0xdc00 + ($adjusted & 0x3ff);
            } else {
                $units[] = $code;
            }
        }

        return $units;
    }
}
