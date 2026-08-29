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

use Kumwe\Producer\Canonical\CanonicalJson;
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

    /**
     * Reconstruct a result only from exact canonical host-result bytes.
     *
     * This is the durable idempotency read path. It accepts precisely one
     * object with `value` and optional `revision`, proves the normal value
     * and revision bounds through the constructor, then requires byte
     * equality with Producer's canonical serialization.
     *
     * @param   string  $bytes  Persisted canonical host-result bytes.
     *
     * @return  self  Reconstructed, fully proved host result.
     *
     * @throws  \InvalidArgumentException  When storage is malformed,
     *                                     non-canonical, or out of contract.
     *
     * @since   0.2.0
     */
    public static function fromCanonicalBytes(string $bytes): self
    {
        try {
            $document = CanonicalJson::decode($bytes);
            if (!$document instanceof \stdClass || !property_exists($document, 'value')) {
                throw new \InvalidArgumentException('A stored host result must be an object carrying value.');
            }
            $members = array_keys(get_object_vars($document));
            sort($members, SORT_STRING);
            if ($members !== ['value'] && $members !== ['revision', 'value']) {
                throw new \InvalidArgumentException('A stored host result carries an unknown member.');
            }
            $revision = property_exists($document, 'revision') ? $document->revision : null;
            if ($revision !== null && !is_string($revision)) {
                throw new \InvalidArgumentException('A stored host result revision must be text.');
            }
            $result = new self($document->value, $revision);
            if (!hash_equals($bytes, CanonicalJson::stringify($result->toDocument()))) {
                throw new \InvalidArgumentException('A stored host result is not canonical.');
            }

            return $result;
        } catch (\InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException('A stored host result is corrupt.', 0, $exception);
        }
    }
}
