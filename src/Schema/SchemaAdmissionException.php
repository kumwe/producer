<?php

/**
 * Refusal raised while admitting a contributed property schema.
 *
 * The profile publishes a closed rejection-code set and a JSON Pointer to
 * the refused schema location; the language-neutral conformance vectors
 * compare exactly those two values across runtimes. The message is a
 * human-readable explanation and never a conformance value.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Schema;

final class SchemaAdmissionException extends \RuntimeException
{
    /**
     * @param string $rejection  Stable code: invalid-root, unsupported-keyword,
     *                           invalid-keyword-value, unsafe-member,
     *                           limit-exceeded, invalid-reference, or
     *                           recursive-schema.
     * @param string $schemaPath JSON Pointer to the refused schema location;
     *                           the empty string is the root.
     * @param string $message    Human diagnostic; never used for matching.
     */
    public function __construct(
        private readonly string $rejection,
        private readonly string $schemaPath,
        string $message
    ) {
        parent::__construct($message);
    }

    public function rejection(): string
    {
        return $this->rejection;
    }

    public function schemaPath(): string
    {
        return $this->schemaPath;
    }
}
