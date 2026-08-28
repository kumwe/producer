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

final class DiagnosticLocation
{
    /**
     * @param list<string>|null $fieldPath Local names, at most 32.
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

    public function artifactId(): ?string
    {
        return $this->artifactId;
    }

    public function nodeId(): ?string
    {
        return $this->nodeId;
    }

    /**
     * @return list<string>|null
     */
    public function fieldPath(): ?array
    {
        return $this->fieldPath;
    }

    public function jsonPointer(): ?string
    {
        return $this->jsonPointer;
    }

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
