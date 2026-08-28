<?php

/**
 * A localizable message reference, common.schema.json#/$defs/messageReference.
 *
 * The key names a catalog entry; the optional default message is a bounded,
 * pre-written fallback. Neither member ever carries request values or host
 * internals: a refusal that must point at data does so through structured
 * diagnostics, keeping the user-facing message non-disclosing by shape.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Error;

/**
 * A localizable message as the contract shapes it: a catalog key plus an
 * optional pre-written fallback.
 *
 * This is the only message type the error layer accepts, which is what
 * makes every user-facing message non-disclosing by construction: both
 * members are validated against the contract grammars at construction and
 * neither has room for interpolated request values, exception text, or
 * host internals.
 *
 * @since   0.1.0
 */
final class MessageReference
{
    /**
     * Proves both members against the contract grammars; an instance that
     * exists is a valid messageReference document.
     *
     * @param   string       $key             The catalog key — a contract
     *                                        qualified name such as
     *                                        `kumwe.producer/unknown-operation`.
     * @param   string|null  $defaultMessage  A pre-written human fallback
     *                                        shown when no catalog entry
     *                                        resolves; UTF-8 text of 1 to
     *                                        500 code points, or null to
     *                                        offer none.
     *
     * @throws  \InvalidArgumentException  When the key is not a qualified
     *                                     name or the fallback breaks its
     *                                     length bound.
     *
     * @since   0.1.0
     */
    public function __construct(
        private readonly string $key,
        private readonly ?string $defaultMessage = null,
    ) {
        if (!ContractGrammar::isQualifiedName($key)) {
            throw new \InvalidArgumentException('A message key must be a contract qualified name.');
        }
        if ($defaultMessage !== null && !ContractGrammar::isBoundedText($defaultMessage, 1, 500)) {
            throw new \InvalidArgumentException('A default message must be UTF-8 text of 1 to 500 code points.');
        }
    }

    /**
     * The catalog key naming this message.
     *
     * @return  string  A contract qualified name.
     *
     * @since   0.1.0
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * The pre-written fallback text, or null when the reference offers
     * only its catalog key.
     *
     * @return  string|null  UTF-8 text of 1 to 500 code points, or null.
     *
     * @since   0.1.0
     */
    public function defaultMessage(): ?string
    {
        return $this->defaultMessage;
    }

    /**
     * The schema-shaped messageReference document; an absent fallback is
     * omitted, never emitted as null.
     *
     * @return  \stdClass  The document ready for canonical serialization.
     *
     * @since   0.1.0
     */
    public function toDocument(): \stdClass
    {
        $document = new \stdClass();
        $document->key = $this->key;
        if ($this->defaultMessage !== null) {
            $document->defaultMessage = $this->defaultMessage;
        }

        return $document;
    }
}
