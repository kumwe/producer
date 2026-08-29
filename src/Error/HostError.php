<?php

/**
 * The canonical host port error document, host-error.schema.json.
 *
 * The taxonomy is closed: twelve stable categories, each built through its
 * own named constructor so the schema's semantic ties hold by construction
 * and a violation is refused, never emitted:
 *
 * - `revision` travels only with `conflict`, where it is optional. An
 *   optimistic-concurrency conflict should return the safe current
 *   revision; other conflicts (such as a live idempotency claim) have no
 *   meaningful revision. This is the exact optionality of the pinned
 *   host-error schema.
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
 * category, so every semantic tie the schema fixes — `revision` only on
 * `conflict`, a retry hint only on a retryable refusal,
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
     * @param   string|null       $revision                The safe current revision, optional on
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
        if (
            ($category === 'rate-limited' && !$retryable)
            || ($category !== 'rate-limited' && $category !== 'unavailable' && $retryable)
        ) {
            throw new \InvalidArgumentException('Retryability does not match the host error category.');
        }
        if ($category !== 'conflict' && $revision !== null) {
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
     * `conflict` — the request conflicts with current host state. Supply a
     * safe current revision for optimistic-concurrency conflicts; omit it
     * when the conflict has no revision coordinate. No other category may
     * carry one. Retryable is fixed false.
     *
     * @param   MessageReference  $message          The non-disclosing refusal message.
     * @param   string|null       $currentRevision  The safe current revision the caller
     *                                              resolves against without a second read,
     *                                              or null when not applicable.
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
        ?string $currentRevision = null,
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
     * Reconstruct an error only from exact canonical host-error bytes.
     *
     * This is the durable replay decode path. It rebuilds every nested
     * value through the same public constructors used for fresh refusals,
     * then requires byte equality with canonical serialization. Unknown,
     * missing, mistyped, non-canonical, or semantically invalid storage is
     * refused rather than remapped.
     *
     * @param   string  $bytes  Persisted canonical host-error bytes.
     *
     * @return  self  Reconstructed, fully proved host error.
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
            if (!$document instanceof \stdClass) {
                throw new \InvalidArgumentException('A stored host error must be an object.');
            }
            if (($document->contractVersion ?? null) !== self::CONTRACT_VERSION
                || ($document->kind ?? null) !== 'host-error'
                || !is_string($document->category ?? null)
                || !is_bool($document->retryable ?? null)
            ) {
                throw new \InvalidArgumentException('A stored host error has invalid required members.');
            }
            $message = self::messageFromDocument($document->message ?? null);
            $diagnostics = [];
            if (property_exists($document, 'diagnostics')) {
                if (!is_array($document->diagnostics) || !array_is_list($document->diagnostics)) {
                    throw new \InvalidArgumentException('Stored host error diagnostics must be a list.');
                }
                foreach ($document->diagnostics as $diagnostic) {
                    $diagnostics[] = self::diagnosticFromDocument($diagnostic);
                }
            }
            $correlationId = property_exists($document, 'correlationId')
                ? $document->correlationId
                : null;
            $retryAfter = property_exists($document, 'retryAfterMilliseconds')
                ? $document->retryAfterMilliseconds
                : null;
            $revision = property_exists($document, 'revision') ? $document->revision : null;
            if (($correlationId !== null && !is_string($correlationId))
                || ($retryAfter !== null && !is_int($retryAfter))
                || ($revision !== null && !is_string($revision))
            ) {
                throw new \InvalidArgumentException('A stored host error has a mistyped optional member.');
            }
            $error = new self(
                $document->category,
                $message,
                $document->retryable,
                $diagnostics,
                $correlationId,
                $retryAfter,
                $revision,
            );
            if (!hash_equals($bytes, $error->toCanonicalJson())) {
                throw new \InvalidArgumentException('A stored host error is not canonical.');
            }

            return $error;
        } catch (\InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException('A stored host error is corrupt.', 0, $exception);
        }
    }

    /**
     * Rebuild one exact message-reference document.
     *
     * @param   mixed  $document  Decoded candidate.
     *
     * @return  MessageReference  Proved message reference.
     *
     * @throws  \InvalidArgumentException  When the candidate is not exact.
     *
     * @since   0.2.0
     */
    private static function messageFromDocument(mixed $document): MessageReference
    {
        if (!$document instanceof \stdClass || !is_string($document->key ?? null)) {
            throw new \InvalidArgumentException('A stored message reference is malformed.');
        }
        $fallback = property_exists($document, 'defaultMessage') ? $document->defaultMessage : null;
        if ($fallback !== null && !is_string($fallback)) {
            throw new \InvalidArgumentException('A stored message fallback must be text.');
        }
        $message = new MessageReference($document->key, $fallback);
        if (CanonicalJson::stringify($document) !== CanonicalJson::stringify($message->toDocument())) {
            throw new \InvalidArgumentException('A stored message reference carries an unknown member.');
        }

        return $message;
    }

    /**
     * Rebuild one exact diagnostic document.
     *
     * @param   mixed  $document  Decoded candidate.
     *
     * @return  Diagnostic  Proved diagnostic.
     *
     * @throws  \InvalidArgumentException  When the candidate is not exact.
     *
     * @since   0.2.0
     */
    private static function diagnosticFromDocument(mixed $document): Diagnostic
    {
        if (!$document instanceof \stdClass
            || !is_string($document->code ?? null)
            || !is_string($document->severity ?? null)
        ) {
            throw new \InvalidArgumentException('A stored diagnostic is malformed.');
        }
        $location = property_exists($document, 'location')
            ? self::locationFromDocument($document->location)
            : null;
        $parameters = [];
        if (property_exists($document, 'parameters')) {
            if (!$document->parameters instanceof \stdClass) {
                throw new \InvalidArgumentException('Stored diagnostic parameters must be an object.');
            }
            $parameters = get_object_vars($document->parameters);
        }
        $remediations = property_exists($document, 'remediations') ? $document->remediations : [];
        if (!is_array($remediations) || !array_is_list($remediations)) {
            throw new \InvalidArgumentException('Stored diagnostic remediations must be a list.');
        }
        $diagnostic = new Diagnostic(
            $document->code,
            $document->severity,
            self::messageFromDocument($document->message ?? null),
            $location,
            $parameters,
            $remediations,
        );
        if (CanonicalJson::stringify($document) !== CanonicalJson::stringify($diagnostic->toDocument())) {
            throw new \InvalidArgumentException('A stored diagnostic carries an unknown member.');
        }

        return $diagnostic;
    }

    /**
     * Rebuild one exact diagnostic-location document.
     *
     * @param   mixed  $document  Decoded candidate.
     *
     * @return  DiagnosticLocation  Proved location.
     *
     * @throws  \InvalidArgumentException  When the candidate is not exact.
     *
     * @since   0.2.0
     */
    private static function locationFromDocument(mixed $document): DiagnosticLocation
    {
        if (!$document instanceof \stdClass) {
            throw new \InvalidArgumentException('A stored diagnostic location must be an object.');
        }
        $artifactId = property_exists($document, 'artifactId') ? $document->artifactId : null;
        $nodeId = property_exists($document, 'nodeId') ? $document->nodeId : null;
        $fieldPath = property_exists($document, 'fieldPath') ? $document->fieldPath : null;
        $jsonPointer = property_exists($document, 'jsonPointer') ? $document->jsonPointer : null;
        if (($artifactId !== null && !is_string($artifactId))
            || ($nodeId !== null && !is_string($nodeId))
            || ($fieldPath !== null && !is_array($fieldPath))
            || ($jsonPointer !== null && !is_string($jsonPointer))
        ) {
            throw new \InvalidArgumentException('A stored diagnostic location has a mistyped member.');
        }
        $location = new DiagnosticLocation($artifactId, $nodeId, $fieldPath, $jsonPointer);
        if (CanonicalJson::stringify($document) !== CanonicalJson::stringify($location->toDocument())) {
            throw new \InvalidArgumentException('A stored diagnostic location carries an unknown member.');
        }

        return $location;
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
     * The safe current revision, present only when a conflict has one.
     *
     * @return  string|null  The safe current revision, or null.
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
