<?php

declare(strict_types=1);

namespace Kumwe\Producer\Schema;

/**
 * Refusal raised while admitting a contributed property schema.
 *
 * The profile publishes a closed rejection-code set and a JSON Pointer to
 * the refused schema location; the language-neutral conformance vectors
 * compare exactly those two values across runtimes. The message is a
 * human-readable explanation and never a conformance value.
 *
 * @since   0.1.0
 */
final class SchemaAdmissionException extends \RuntimeException
{
    /**
     * Hold the refusal's stable code and schema pointer alongside its
     * human-readable message.
     *
     * @param string $rejection  Stable code: invalid-root, unsupported-keyword,
     *                           invalid-keyword-value, unsafe-member,
     *                           limit-exceeded, invalid-reference, or
     *                           recursive-schema.
     * @param string $schemaPath JSON Pointer to the refused schema location;
     *                           the empty string is the root.
     * @param string $message    Human diagnostic; never used for matching.
     *
     * @since   0.1.0
     */
    public function __construct(
        private readonly string $rejection,
        private readonly string $schemaPath,
        string $message
    ) {
        parent::__construct($message);
    }

    /**
     * The stable rejection code, one of the profile's closed seven:
     * `invalid-root` when the document is not a schema object, breaks a
     * root invariant, or aliases or cycles a JSON object;
     * `unsupported-keyword` for a keyword outside the closed set;
     * `invalid-keyword-value` for an allowed keyword whose operand breaks
     * its grammar; `unsafe-member` for an empty, prototype-polluting, or
     * control-character member name; `limit-exceeded` when a published
     * `$defs/limits` ceiling is passed; `invalid-reference` for a `$ref`
     * outside the portable local grammar or not resolving to a schema
     * position; and `recursive-schema` for a reference edge that closes a
     * cycle.
     *
     * @since   0.1.0
     */
    public function rejection(): string
    {
        return $this->rejection;
    }

    /**
     * JSON Pointer to the refused schema location; the empty string is the
     * root.
     *
     * @since   0.1.0
     */
    public function schemaPath(): string
    {
        return $this->schemaPath;
    }
}
