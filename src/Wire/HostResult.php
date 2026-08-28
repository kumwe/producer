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

/**
 * One successful port outcome, proven serializable at construction.
 *
 * The value must fit the contract's jsonValue shape and the optional
 * revision must satisfy the revision grammar, both checked here — so a
 * HostResult that exists can always take canonical wire form. A
 * concurrency-protected operation must return the advanced revision;
 * {@see Dispatcher} fails closed as internal when it does not.
 *
 * @since   0.1.0
 */
final class HostResult
{
    /**
     * Proves the outcome fits the wire before it can circulate.
     *
     * @param   mixed        $value     The operation's result value — any
     *                                  contract jsonValue; explicitly null
     *                                  when the operation answers with
     *                                  nothing.
     * @param   string|null  $revision  The advanced revision after a
     *                                  concurrency-protected mutation, or
     *                                  null for every other operation.
     *
     * @throws  JsonShapeViolation         When the value breaks the
     *                                     jsonValue bounds.
     * @throws  \InvalidArgumentException  When the revision breaks the
     *                                     revision grammar.
     *
     * @since   0.1.0
     */
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
     *
     * @return  \stdClass  The document with `value` always present — null
     *                     stays explicit — and `revision` omitted when
     *                     absent.
     *
     * @since   0.1.0
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
