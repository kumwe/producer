<?php

/**
 * The body a host returns when a port operation succeeds,
 * host-result.schema.json.
 *
 * An operation that answers with nothing carries an explicit null value,
 * never an absent member, so absence stays meaningful. The value is
 * proven to fit the contract's jsonValue shape at construction, which is
 * what lets {@see StrictResponder} promise canonical bytes for every
 * accepted result.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire;

use Kumwe\Producer\Error\ContractGrammar;

final class HostResult
{
    public function __construct(
        public readonly mixed $value,
        public readonly ?string $revision = null,
    ) {
        JsonValueGuard::assert($value);
        if ($revision !== null && !ContractGrammar::isRevision($revision)) {
            throw new \InvalidArgumentException('A result revision must be UTF-8 text of 1 to 200 code points.');
        }
    }

    /**
     * The schema-shaped result document.
     */
    public function toDocument(): \stdClass
    {
        $document = new \stdClass();
        if ($this->revision !== null) {
            $document->revision = $this->revision;
        }
        $document->value = $this->value;

        return $document;
    }
}
