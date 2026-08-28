<?php

declare(strict_types=1);

namespace Kumwe\Producer\Schema;

/**
 * One instance-validation failure in the profile's portable diagnostic shape.
 *
 * The conformance corpus compares the failing keyword and the instance JSON
 * Pointer of the first diagnostic; the message is a human-readable default
 * and not a conformance value.
 *
 * @since   0.1.0
 */
final class SchemaInstanceDiagnostic
{
    /**
     * Hold one failure's portable coordinates and default message.
     *
     * @param string $instancePath JSON Pointer to the failing instance
     *                             location; the empty string is the root.
     * @param string $keyword      The schema keyword that failed.
     * @param string $message      Human-readable default message.
     *
     * @since   0.1.0
     */
    public function __construct(
        public readonly string $instancePath,
        public readonly string $keyword,
        public readonly string $message
    ) {
    }
}
