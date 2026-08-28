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

/**
 * One refusal from the closed host error taxonomy, valid by construction.
 *
 * Instances exist only through the twelve named constructors, one per
 * category, so every semantic tie the schema fixes — `revision` only and
 * always on `conflict`, a retry hint only on a retryable refusal,
 * `retryable` false on every deterministic category — holds before the
 * document can be serialized. Free-text matching is never needed: the
 * category string is the stable code a caller switches on.
 *
 * @since   0.1.0
 */
final class HostError
{
    /**
     * The pinned contract version every emitted error document declares.
     *
     * @since   0.1.0
     */
    public const CONTRACT_VERSION = '0.1-draft';

    /**
     * The twelve stable categories, the closed vocabulary of
     * host-error.schema.json — the only refusal codes on the wire.
     *
     * @since   0.1.0
     */
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

    /**
     * The largest admissible retry hint: 24 hours in milliseconds, the
     * schema's upper bound on `retryAfterMilliseconds`.
     *
     * @since   0.1.0
     */
    public const MAXIMUM_RETRY_AFTER_MILLISECONDS = 86400000;

    /**
     * Enforces every semantic tie of the taxonomy; only the twelve named
     * constructors reach it, each fixing the members its category permits.
     *
     * @param   string            $category                One of the twelve stable categories.
     * @param   MessageReference  $message                 The non-disclosing refusal message.
     * @param   bool              $retryable               Whether retrying the identical request
     *                                                     can succeed.
     * @param   list<Diagnostic>  $diagnostics             At most 1000 structured diagnostics.
     * @param   string|null       $correlationId           A host trace identifier — a contract
     *                                                     stable id — or null.
     * @param   int|null          $retryAfterMilliseconds  A retry hint, only with a retryable
     *                                                     refusal, 0 to 86400000.
     * @param   string|null       $revision                The safe current revision, required on
     *                                                     conflict and forbidden elsewhere.
     *
     * @throws  \InvalidArgumentException  When any member breaks its bound
     *                                     or a category's semantic tie.
     *
     * @since   0.1.0
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
     * `invalid-request` — the request itself violates the wire contract
     * (malformed envelope, unknown operation, misused idempotency key).
     * Deterministic, so retryable is fixed false.
     *
     * @param   MessageReference  $message        The non-disclosing refusal message.
     * @param   list<Diagnostic>  $diagnostics    At most 1000 structured diagnostics.
     * @param   string|null       $correlationId  A host trace identifier, or null.
     *
     * @return  self  The refusal, valid by construction.
     *
     * @throws  \InvalidArgumentException  When a member breaks its bound.
     *
     * @since   0.1.0
     */
    public static function invalidRequest(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('invalid-request', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * `unauthenticated` — the caller presented no identity the host
     * accepts. Retryable is fixed false: only new credentials, not a
     * retry, can change the outcome.
     *
     * @param   MessageReference  $message        The non-disclosing refusal message.
     * @param   list<Diagnostic>  $diagnostics    At most 1000 structured diagnostics.
     * @param   string|null       $correlationId  A host trace identifier, or null.
     *
     * @return  self  The refusal, valid by construction.
     *
     * @throws  \InvalidArgumentException  When a member breaks its bound.
     *
     * @since   0.1.0
     */
    public static function unauthenticated(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('unauthenticated', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * `forbidden` — the caller is known but the host's authority refuses
     * this operation. Retryable is fixed false.
     *
     * @param   MessageReference  $message        The non-disclosing refusal message.
     * @param   list<Diagnostic>  $diagnostics    At most 1000 structured diagnostics.
     * @param   string|null       $correlationId  A host trace identifier, or null.
     *
     * @return  self  The refusal, valid by construction.
     *
     * @throws  \InvalidArgumentException  When a member breaks its bound.
     *
     * @since   0.1.0
     */
    public static function forbidden(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('forbidden', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * `not-found` — the addressed resource is not in the caller's view;
     * whether it is absent or merely hidden stays undisclosed. Retryable
     * is fixed false.
     *
     * @param   MessageReference  $message        The non-disclosing refusal message.
     * @param   list<Diagnostic>  $diagnostics    At most 1000 structured diagnostics.
     * @param   string|null       $correlationId  A host trace identifier, or null.
     *
     * @return  self  The refusal, valid by construction.
     *
     * @throws  \InvalidArgumentException  When a member breaks its bound.
     *
     * @since   0.1.0
     */
    public static function notFound(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('not-found', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * `conflict` — an expectedRevision mismatch on a concurrency-protected
     * operation. The safe current revision is mandatory here and travels
     * with no other category. Retryable is fixed false: the caller must
     * resolve against the returned revision, not repeat the request.
     *
     * @param   MessageReference  $message          The non-disclosing refusal message.
     * @param   string            $currentRevision  The safe current revision the caller
     *                                              resolves against without a second read.
     * @param   list<Diagnostic>  $diagnostics      At most 1000 structured diagnostics.
     * @param   string|null       $correlationId    A host trace identifier, or null.
     *
     * @return  self  The refusal, valid by construction.
     *
     * @throws  \InvalidArgumentException  When a member breaks its bound.
     *
     * @since   0.1.0
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
     * `validation-failed` — the argument fits the wire shape but the
     * host's semantic validation refuses it; the diagnostics say where.
     * Retryable is fixed false.
     *
     * @param   MessageReference  $message        The non-disclosing refusal message.
     * @param   list<Diagnostic>  $diagnostics    At most 1000 structured diagnostics.
     * @param   string|null       $correlationId  A host trace identifier, or null.
     *
     * @return  self  The refusal, valid by construction.
     *
     * @throws  \InvalidArgumentException  When a member breaks its bound.
     *
     * @since   0.1.0
     */
    public static function validationFailed(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('validation-failed', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * `incompatible` — the request speaks a protocol or contract version
     * this producer does not support. Retryable is fixed false.
     *
     * @param   MessageReference  $message        The non-disclosing refusal message.
     * @param   list<Diagnostic>  $diagnostics    At most 1000 structured diagnostics.
     * @param   string|null       $correlationId  A host trace identifier, or null.
     *
     * @return  self  The refusal, valid by construction.
     *
     * @throws  \InvalidArgumentException  When a member breaks its bound.
     *
     * @since   0.1.0
     */
    public static function incompatible(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('incompatible', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * `limit-exceeded` — a size or count bound refused the request before
     * the work it would have caused. Retryable is fixed false: the same
     * request will always break the same bound.
     *
     * @param   MessageReference  $message        The non-disclosing refusal message.
     * @param   list<Diagnostic>  $diagnostics    At most 1000 structured diagnostics.
     * @param   string|null       $correlationId  A host trace identifier, or null.
     *
     * @return  self  The refusal, valid by construction.
     *
     * @throws  \InvalidArgumentException  When a member breaks its bound.
     *
     * @since   0.1.0
     */
    public static function limitExceeded(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('limit-exceeded', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * `rate-limited` — the caller is over a rate window. Retryable by
     * definition (per the host sequence vectors), so this is the one
     * category whose retry hint is always permitted.
     *
     * @param   MessageReference  $message                 The non-disclosing refusal message.
     * @param   int|null          $retryAfterMilliseconds  When to retry, 0 to 86400000
     *                                                     milliseconds, or null for no hint.
     * @param   list<Diagnostic>  $diagnostics             At most 1000 structured diagnostics.
     * @param   string|null       $correlationId           A host trace identifier, or null.
     *
     * @return  self  The refusal, retryable true by construction.
     *
     * @throws  \InvalidArgumentException  When a member breaks its bound.
     *
     * @since   0.1.0
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
     * `unavailable` — the host or a dependency cannot serve the request
     * right now. Only here does the host choose retryability, and a retry
     * hint is admitted only when it declares the outage retryable.
     *
     * @param   MessageReference  $message                 The non-disclosing refusal message.
     * @param   bool              $retryable               Whether the outage is transient. Defaults
     *                                                     closed: an unavailability of unknown shape
     *                                                     promises nothing.
     * @param   int|null          $retryAfterMilliseconds  When to retry, 0 to 86400000 milliseconds;
     *                                                     only with $retryable true, or null.
     * @param   list<Diagnostic>  $diagnostics             At most 1000 structured diagnostics.
     * @param   string|null       $correlationId           A host trace identifier, or null.
     *
     * @return  self  The refusal, valid by construction.
     *
     * @throws  \InvalidArgumentException  When a member breaks its bound or
     *                                     a hint accompanies a non-retryable
     *                                     outage.
     *
     * @since   0.1.0
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
     * `cancelled` — the operation was superseded or cancelled before it
     * completed, per the pinned sequence vectors. Retryable is fixed
     * false: the cancelled work is not resumed by repeating it.
     *
     * @param   MessageReference  $message        The non-disclosing refusal message.
     * @param   list<Diagnostic>  $diagnostics    At most 1000 structured diagnostics.
     * @param   string|null       $correlationId  A host trace identifier, or null.
     *
     * @return  self  The refusal, valid by construction.
     *
     * @throws  \InvalidArgumentException  When a member breaks its bound.
     *
     * @since   0.1.0
     */
    public static function cancelled(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('cancelled', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * `internal` — an unexpected host failure, disclosed as nothing more
     * than that. Retryable is fixed false; the correlation id, not the
     * message, is the channel for tracing what happened.
     *
     * @param   MessageReference  $message        The non-disclosing refusal message.
     * @param   list<Diagnostic>  $diagnostics    At most 1000 structured diagnostics.
     * @param   string|null       $correlationId  A host trace identifier, or null.
     *
     * @return  self  The refusal, valid by construction.
     *
     * @throws  \InvalidArgumentException  When a member breaks its bound.
     *
     * @since   0.1.0
     */
    public static function internal(
        MessageReference $message,
        array $diagnostics = [],
        ?string $correlationId = null,
    ): self {
        return new self('internal', $message, false, $diagnostics, $correlationId, null, null);
    }

    /**
     * The stable category code a caller switches on.
     *
     * @return  string  One of the twelve stable categories.
     *
     * @since   0.1.0
     */
    public function category(): string
    {
        return $this->category;
    }

    /**
     * The non-disclosing refusal message.
     *
     * @return  MessageReference  A catalog key plus optional fallback.
     *
     * @since   0.1.0
     */
    public function message(): MessageReference
    {
        return $this->message;
    }

    /**
     * Whether retrying the identical request can succeed — true only for
     * rate-limited and a host-declared transient unavailability.
     *
     * @return  bool  The retryability the category fixes.
     *
     * @since   0.1.0
     */
    public function retryable(): bool
    {
        return $this->retryable;
    }

    /**
     * The structured diagnostics pointing at the offending data.
     *
     * @return  list<Diagnostic>  At most 1000 entries.
     *
     * @since   0.1.0
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * The host trace identifier for support, when one was attached.
     *
     * @return  string|null  A contract stable identifier, or null.
     *
     * @since   0.1.0
     */
    public function correlationId(): ?string
    {
        return $this->correlationId;
    }

    /**
     * The retry hint, present only on a retryable refusal.
     *
     * @return  int|null  0 to 86400000 milliseconds, or null.
     *
     * @since   0.1.0
     */
    public function retryAfterMilliseconds(): ?int
    {
        return $this->retryAfterMilliseconds;
    }

    /**
     * The safe current revision, present exactly when the category is
     * conflict.
     *
     * @return  string|null  The revision a conflict must return, or null.
     *
     * @since   0.1.0
     */
    public function revision(): ?string
    {
        return $this->revision;
    }

    /**
     * The schema-shaped error document. Optional members absent by policy
     * are omitted, never emitted as null.
     *
     * @return  \stdClass  The document ready for canonical serialization.
     *
     * @since   0.1.0
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
     *
     * @return  string  Canonical JSON, identical across conforming
     *                  runtimes.
     *
     * @since   0.1.0
     */
    public function toCanonicalJson(): string
    {
        return CanonicalJson::stringify($this->toDocument());
    }
}
