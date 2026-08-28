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

/**
 * Pure predicates over the pinned contract's common string grammars.
 *
 * Each predicate admits exactly what its $defs entry in the vendored
 * common.schema.json admits — same pattern, same length bound, same
 * forbidden member names — so the envelope and error layers share one
 * authoritative reading of the contract's lexical rules. Every predicate
 * is total and side-effect free: any string, valid UTF-8 or not, yields a
 * boolean, and a string that is not valid UTF-8 satisfies no predicate.
 *
 * @since   0.1.0
 */
final class ContractGrammar
{
    /**
     * The member names every name grammar refuses outright — `__proto__`,
     * `prototype`, and `constructor` — because they collide with prototype
     * machinery in JavaScript consumers of the same contract documents.
     *
     * @since   0.1.0
     */
    private const FORBIDDEN_MEMBER_NAMES = ['__proto__', 'prototype', 'constructor'];

    /**
     * Not constructable: the grammar is a closed set of static predicates.
     *
     * @since   0.1.0
     */
    private function __construct()
    {
    }

    /**
     * common.schema.json#/$defs/qualifiedName — a namespaced contract name
     * such as `studio.operation/artifact.save`: lowercase alphanumeric
     * segments joined by `.` or `-` on both sides of exactly one `/`, at
     * most 160 characters (the grammar is ASCII, so bytes and code points
     * agree).
     *
     * @param   string  $value  The candidate string.
     *
     * @return  bool  True only when the value satisfies the pinned grammar.
     *
     * @since   0.1.0
     */
    public static function isQualifiedName(string $value): bool
    {
        return strlen($value) <= 160
            && preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*\/[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*\z/', $value) === 1;
    }

    /**
     * common.schema.json#/$defs/localName — an unqualified member-style
     * name: lowercase alphanumeric segments joined by `.`, `_`, or `-`,
     * starting with a letter, at most 100 characters, and never one of the
     * forbidden member names.
     *
     * @param   string  $value  The candidate string.
     *
     * @return  bool  True only when the value satisfies the pinned grammar.
     *
     * @since   0.1.0
     */
    public static function isLocalName(string $value): bool
    {
        return strlen($value) <= 100
            && !in_array($value, self::FORBIDDEN_MEMBER_NAMES, true)
            && preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $value) === 1;
    }

    /**
     * common.schema.json#/$defs/stableId — an opaque host identifier:
     * ASCII letters, digits, and `.`, `_`, `:`, `/`, `-` after an
     * alphanumeric first character, at most 240 characters, and never one
     * of the forbidden member names.
     *
     * @param   string  $value  The candidate string.
     *
     * @return  bool  True only when the value satisfies the pinned grammar.
     *
     * @since   0.1.0
     */
    public static function isStableId(string $value): bool
    {
        return strlen($value) <= 240
            && !in_array($value, self::FORBIDDEN_MEMBER_NAMES, true)
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]*\z/', $value) === 1;
    }

    /**
     * common.schema.json#/$defs/safeJsonMemberName — a JSON object member
     * name a contract document may carry: valid UTF-8 of 1 to 200 code
     * points, no C0 or DEL control characters, and never one of the
     * forbidden member names.
     *
     * @param   string  $value  The candidate string.
     *
     * @return  bool  True only when the value satisfies the pinned grammar.
     *
     * @since   0.1.0
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
     * common.schema.json#/$defs/revision — an opaque revision token: any
     * valid UTF-8 text of 1 to 200 code points. The contract attaches no
     * structure to a revision; only the host that minted it can compare
     * two of them for meaning.
     *
     * @param   string  $value  The candidate string.
     *
     * @return  bool  True only when the value satisfies the pinned grammar.
     *
     * @since   0.1.0
     */
    public static function isRevision(string $value): bool
    {
        return self::isBoundedText($value, 1, 200);
    }

    /**
     * common.schema.json#/$defs/semanticVersion — a Semantic Versioning
     * 2.0.0 version: MAJOR.MINOR.PATCH with optional pre-release and
     * build-metadata suffixes, no leading zeros in numeric parts, at most
     * 100 characters.
     *
     * @param   string  $value  The candidate string.
     *
     * @return  bool  True only when the value satisfies the pinned grammar.
     *
     * @since   0.1.0
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
     * common.schema.json#/$defs/locale — a BCP 47-shaped language tag: a
     * primary subtag of 2 to 8 ASCII letters followed by `-`-joined
     * subtags of 1 to 8 alphanumerics, at most 50 characters.
     *
     * @param   string  $value  The candidate string.
     *
     * @return  bool  True only when the value satisfies the pinned grammar.
     *
     * @since   0.1.0
     */
    public static function isLocale(string $value): bool
    {
        return strlen($value) <= 50
            && preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*\z/', $value) === 1;
    }

    /**
     * Valid UTF-8 text whose code-point length lies inside the inclusive
     * bound — the length rule every bounded string type in the contract
     * shares, measured the way JSON Schema measures it.
     *
     * @param   string  $value          The candidate string.
     * @param   int     $minimumLength  The smallest admissible code-point
     *                                  count, inclusive.
     * @param   int     $maximumLength  The largest admissible code-point
     *                                  count, inclusive.
     *
     * @return  bool  True only when the value is valid UTF-8 and its
     *                code-point length lies inside the bound.
     *
     * @since   0.1.0
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
