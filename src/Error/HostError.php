<?php

/**
 * The canonical host port error document, host-error.schema.json.
 *
 * The taxonomy is closed: twelve stable categories, each built through its
 * own named constructor so the schema's semantic ties hold by construction
 * and a violation is refused, never emitted:
 *
 * - `revision` travels only with `conflict`, where it is required — the
 *   host-request schema fixes that "a mismatch conflicts and the host
 *   returns the safe current revision", and the host conformance vector
 *   schema calls it "the safe current revision a conflict MUST return".
 * - `retryAfterMilliseconds` is a retry hint, so it travels only with a
 *   retryable refusal: always permitted on `rate-limited` (retryable by
 *   definition, per the host sequence vectors) and on `unavailable` only
 *   when the host declares that outage retryable.
 * - Every other category is a deterministic refusal of this exact request,
 *   so `retryable` is fixed false there — the reading every pinned host
 *   conformance vector expects.
 *
 * Messages are non-disclosing by shape: a constructor accepts only a
 * MessageReference (catalog key plus bounded pre-written fallback) and
 * bounded structured diagnostics. There is no parameter through which raw
 * internals — exception text, resource values, request bodies — can reach
 * the user-facing message.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Error;

use Kumwe\Producer\Canonical\CanonicalJson;

final class HostError
{
    public const CONTRACT_VERSION = '0.1-draft';

    public const CATEGORIES = [
        'invalid-request',
        'unauthenticated',
        'forbidden',
        'not-found',
        'conflict',
        'validation-failed',
        'incompatible',
        'limit-exceeded',
        'rate-limited',
        'unavailable',
        'cancelled',
        'internal',
    ];

    public const MAXIMUM_RETRY_AFTER_MILLISECONDS = 86400000;

    /**
     * @param list<Diagnostic> $diagnostics
     */
    private function __construct(
        private readonly string $category,
        private readonly MessageReference $message,
        private readonly bool $retryable,
        private readonly array $diagnostics,
        private readonly ?string $correlationId,
        private readonly ?int $retryAfterMilliseconds,
        private readonly ?string $revision,
    ) {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('A host error category must be one of the twelve stable categories.');
        }
        if (!array_is_list($diagnostics) || count($diagnostics) > 1000) {
            throw new \InvalidArgumentException('Host error diagnostics must be a list of at most 1000 entries.');
        }
        foreach ($diagnostics as $diagnostic) {
            if (!$diagnostic instanceof Diagnostic) {
                throw new \InvalidArgumentException('Every host error diagnostic must be a Diagnostic.');
            }
        }
        if ($correlationId !== null && !ContractGrammar::isStableId($correlationId)) {
            throw new \InvalidArgumentException('A correlationId must be a contract stable identifier.');
        }
        if ($retryAfterMilliseconds !== null) {
            if (!$retryable) {
                throw new \InvalidArgumentException('A retry hint is meaningless on a non-retryable refusal.');
            }
            if ($retryAfterMilliseconds < 0 || $retryAfterMilliseconds > self::MAXIMUM_RETRY_AFTER_MILLISECONDS) {
                throw new \InvalidArgumentException('retryAfterMilliseconds must lie between 0 and 86400000.');
            }
        }
        if ($category === 'conflict') {
            if ($revision === null) {
                throw new \InvalidArgumentException('A conflict must return the safe current revision.');
            }
        } elseif ($revision !== null) {
            throw new \InvalidArgumentException('Only a conflict carries the safe current revision.');
        }
        if ($revision !== null && !ContractGrammar::isRevision($revision)) {
            throw new \InvalidArgumentException('A revision must be UTF-8 text of 1 to 200 code points.');
        }
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    public static function invalidRequest(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('invalid-request', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    public static function unauthenticated(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('unauthenticated', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    public static function forbidden(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('forbidden', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    public static function notFound(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('not-found', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * @param string           $currentRevision The safe current revision the caller
     *                                          resolves against without a second read.
     * @param list<Diagnostic> $diagnostics
     */
    public static function conflict(
        MessageReference $message,
        string $currentRevision,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('conflict', $message, false, $diagnostics, $correlationId, null, $currentRevision);
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    public static function validationFailed(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('validation-failed', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    public static function incompatible(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('incompatible', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    public static function limitExceeded(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('limit-exceeded', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    public static function rateLimited(
        MessageReference $message,
        ?int $retryAfterMilliseconds = null,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('rate-limited', $message, true, $diagnostics, $correlationId, $retryAfterMilliseconds, null);
    }

    /**
     * @param bool             $retryable   Whether the outage is transient. Defaults
     *                                      closed: an unavailability of unknown shape
     *                                      promises nothing.
     * @param list<Diagnostic> $diagnostics
     */
    public static function unavailable(
        MessageReference $message,
        bool $retryable = false,
        ?int $retryAfterMilliseconds = null,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('unavailable', $message, $retryable, $diagnostics, $correlationId, $retryAfterMilliseconds, null);
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    public static function cancelled(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('cancelled', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    public static function internal(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('internal', $message, false, $diagnostics, $correlationId, null, null);
    }

    public function category(): string
    {
        return $this->category;
    }

    public function message(): MessageReference
    {
        return $this->message;
    }

    public function retryable(): bool
    {
        return $this->retryable;
    }

    /**
     * @return list<Diagnostic>
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }

    public function retryAfterMilliseconds(): ?int
    {
        return $this->retryAfterMilliseconds;
    }

    public function revision(): ?string
    {
        return $this->revision;
    }

    /**
     * The schema-shaped error document. Optional members absent by policy
     * are omitted, never emitted as null.
     */
    public function toDocument(): \stdClass
    {
        $document = new \stdClass();
        $document->contractVersion = self::CONTRACT_VERSION;
        $document->kind = 'host-error';
        $document->category = $this->category;
        $document->message = $this->message->toDocument();
        $document->retryable = $this->retryable;
        if ($this->correlationId !== null) {
            $document->correlationId = $this->correlationId;
        }
        if ($this->retryAfterMilliseconds !== null) {
            $document->retryAfterMilliseconds = $this->retryAfterMilliseconds;
        }
        if ($this->revision !== null) {
            $document->revision = $this->revision;
        }
        if ($this->diagnostics !== []) {
            $document->diagnostics = array_map(
                static fn (Diagnostic $diagnostic): \stdClass => $diagnostic->toDocument(),
                $this->diagnostics
            );
        }

        return $document;
    }

    /**
     * The exact canonical bytes of the error document.
     */
    public function toCanonicalJson(): string
    {
        return CanonicalJson::stringify($this->toDocument());
    }
}
