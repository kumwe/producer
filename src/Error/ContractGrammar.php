<?php

/**
 * The lexical grammar of the pinned contract's common string types.
 *
 * Every predicate mirrors one $defs entry in the vendored
 * common.schema.json: same pattern, same bound, same forbidden member
 * names. JSON Schema length bounds count Unicode code points, so bounded
 * checks measure with mb_strlen after proving the bytes are UTF-8; a
 * string that is not valid UTF-8 never satisfies any predicate.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Error;

final class ContractGrammar
{
    private const FORBIDDEN_MEMBER_NAMES = ['__proto__', 'prototype', 'constructor'];

    private function __construct()
    {
    }

    /**
     * common.schema.json#/$defs/qualifiedName
     */
    public static function isQualifiedName(string $value): bool
    {
        return strlen($value) <= 160
            && preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*\/[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*\z/', $value) === 1;
    }

    /**
     * common.schema.json#/$defs/localName
     */
    public static function isLocalName(string $value): bool
    {
        return strlen($value) <= 100
            && !in_array($value, self::FORBIDDEN_MEMBER_NAMES, true)
            && preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $value) === 1;
    }

    /**
     * common.schema.json#/$defs/stableId
     */
    public static function isStableId(string $value): bool
    {
        return strlen($value) <= 240
            && !in_array($value, self::FORBIDDEN_MEMBER_NAMES, true)
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]*\z/', $value) === 1;
    }

    /**
     * common.schema.json#/$defs/safeJsonMemberName
     */
    public static function isSafeJsonMemberName(string $value): bool
    {
        if (!mb_check_encoding($value, 'UTF-8') || in_array($value, self::FORBIDDEN_MEMBER_NAMES, true)) {
            return false;
        }
        $length = mb_strlen($value, 'UTF-8');
        if ($length < 1 || $length > 200) {
            return false;
        }

        return preg_match('/[\x00-\x1f\x7f]/', $value) !== 1;
    }

    /**
     * common.schema.json#/$defs/revision
     */
    public static function isRevision(string $value): bool
    {
        return self::isBoundedText($value, 1, 200);
    }

    /**
     * common.schema.json#/$defs/semanticVersion
     */
    public static function isSemanticVersion(string $value): bool
    {
        return strlen($value) <= 100
            && preg_match(
                '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)'
                    . '(?:-(?:0|[1-9][0-9]*|[0-9]*[A-Za-z-][0-9A-Za-z-]*)'
                    . '(?:\.(?:0|[1-9][0-9]*|[0-9]*[A-Za-z-][0-9A-Za-z-]*))*)?'
                    . '(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?\z/',
                $value
            ) === 1;
    }

    /**
     * common.schema.json#/$defs/locale
     */
    public static function isLocale(string $value): bool
    {
        return strlen($value) <= 50
            && preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*\z/', $value) === 1;
    }

    /**
     * Valid UTF-8 text whose code-point length lies inside the bound.
     */
    public static function isBoundedText(string $value, int $minimumLength, int $maximumLength): bool
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            return false;
        }
        $length = mb_strlen($value, 'UTF-8');

        return $length >= $minimumLength && $length <= $maximumLength;
    }
}
