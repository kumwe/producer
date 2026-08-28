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

final class MessageReference
{
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

    public function key(): string
    {
        return $this->key;
    }

    public function defaultMessage(): ?string
    {
        return $this->defaultMessage;
    }

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
