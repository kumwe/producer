<?php

/**
 * One instance-validation failure in the profile's portable diagnostic shape.
 *
 * The conformance corpus compares the failing keyword and the instance JSON
 * Pointer of the first diagnostic; the message is a human-readable default
 * and not a conformance value.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Schema;

final class SchemaInstanceDiagnostic
{
    /**
     * @param string $instancePath JSON Pointer to the failing instance
     *                             location; the empty string is the root.
     * @param string $keyword      The schema keyword that failed.
     * @param string $message      Human-readable default message.
     */
    public function __construct(
        public readonly string $instancePath,
        public readonly string $keyword,
        public readonly string $message
    ) {
    }
}
