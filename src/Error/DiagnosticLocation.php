<?php

/**
 * Where a diagnostic points, common.schema.json#/$defs/diagnosticLocation.
 *
 * Every member is optional and bounded; the schema permits an empty
 * location, so this class does too.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Error;

/**
 * The place a diagnostic points at — an artifact, a node, a field path, a
 * JSON Pointer — each member optional and proven against its contract
 * grammar at construction.
 *
 * A location names where the problem sits; it never carries the offending
 * value itself. The schema permits an entirely empty location, so every
 * member defaults to null and no combination is required.
 *
 * @since   0.1.0
 */
final class DiagnosticLocation
{
    /**
     * Proves every provided member against the contract grammars; an
     * instance that exists is a valid diagnosticLocation document.
     *
     * @param   string|null        $artifactId   The artifact concerned — a
     *                                           contract stable identifier,
     *                                           or null.
     * @param   string|null        $nodeId       The node inside it — a
     *                                           contract stable identifier,
     *                                           or null.
     * @param   list<string>|null  $fieldPath    Local names, at most 32.
     * @param   string|null        $jsonPointer  A JSON Pointer of at most
     *                                           1000 code points, or null.
     *
     * @throws  \InvalidArgumentException  When any provided member breaks
     *                                     its grammar or bound.
     *
     * @since   0.1.0
     */
    public function __construct(
        private readonly ?string $artifactId = null,
        private readonly ?string $nodeId = null,
        private readonly ?array $fieldPath = null,
        private readonly ?string $jsonPointer = null,
    ) {
        if ($artifactId !== null && !ContractGrammar::isStableId($artifactId)) {
            throw new \InvalidArgumentException('A diagnostic artifactId must be a contract stable identifier.');
        }
        if ($nodeId !== null && !ContractGrammar::isStableId($nodeId)) {
            throw new \InvalidArgumentException('A diagnostic nodeId must be a contract stable identifier.');
        }
        if ($fieldPath !== null) {
            if (!array_is_list($fieldPath) || count($fieldPath) > 32) {
                throw new \InvalidArgumentException('A diagnostic fieldPath must be a list of at most 32 segments.');
            }
            foreach ($fieldPath as $segment) {
                if (!is_string($segment) || !ContractGrammar::isLocalName($segment)) {
                    throw new \InvalidArgumentException('Every fieldPath segment must be a contract local name.');
                }
            }
        }
        if ($jsonPointer !== null && !ContractGrammar::isBoundedText($jsonPointer, 0, 1000)) {
            throw new \InvalidArgumentException('A diagnostic jsonPointer must be UTF-8 text of at most 1000 code points.');
        }
    }

    /**
     * The artifact this location concerns, when one is named.
     *
     * @return  string|null  A contract stable identifier, or null.
     *
     * @since   0.1.0
     */
    public function artifactId(): ?string
    {
        return $this->artifactId;
    }

    /**
     * The node this location concerns, when one is named.
     *
     * @return  string|null  A contract stable identifier, or null.
     *
     * @since   0.1.0
     */
    public function nodeId(): ?string
    {
        return $this->nodeId;
    }

    /**
     * The field path into the located value, when one is named.
     *
     * @return  list<string>|null  At most 32 contract local names, or null.
     *
     * @since   0.1.0
     */
    public function fieldPath(): ?array
    {
        return $this->fieldPath;
    }

    /**
     * The JSON Pointer into the located document, when one is named.
     *
     * @return  string|null  A pointer of at most 1000 code points, or null.
     *
     * @since   0.1.0
     */
    public function jsonPointer(): ?string
    {
        return $this->jsonPointer;
    }

    /**
     * The schema-shaped diagnosticLocation document; absent members are
     * omitted, never emitted as null, and an empty location is an empty
     * object.
     *
     * @return  \stdClass  The document ready for canonical serialization.
     *
     * @since   0.1.0
     */
    public function toDocument(): \stdClass
    {
        $document = new \stdClass();
        if ($this->artifactId !== null) {
            $document->artifactId = $this->artifactId;
        }
        if ($this->nodeId !== null) {
            $document->nodeId = $this->nodeId;
        }
        if ($this->fieldPath !== null) {
            $document->fieldPath = $this->fieldPath;
        }
        if ($this->jsonPointer !== null) {
            $document->jsonPointer = $this->jsonPointer;
        }

        return $document;
    }
}
