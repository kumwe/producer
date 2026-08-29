<?php

/**
 * Parse and validate one host port request body, host-request.schema.json.
 *
 * A malformed envelope is refused as invalid-request rather than partially
 * honoured; a wire version this producer does not speak is refused as
 * incompatible before any work; an oversize body is refused as
 * limit-exceeded before it is decoded. Refusal messages are fixed catalog
 * references — the offending structure is named through bounded
 * diagnostics (a member name, a JSON Pointer), never through echoed
 * request values.
 *
 * Duplicate object members are detected exactly: after json_decode proves
 * the text well-formed (PHP alone would silently keep the last duplicate),
 * a linear scan of the accepted text tracks every object's decoded member
 * names — an escape-spelled name collides with its raw spelling, because
 * both decode to the same name — and any repeat is refused.
 *
 * Validation order is deliberate: size bound, UTF-8, well-formedness,
 * duplicate members, envelope structure and member grammar, then wire
 * version support (incompatible), then registry membership of the
 * operation. Cross-checks that need the resolved operation — expected
 * revision and idempotency key applicability, route agreement — belong to
 * {@see Dispatcher}.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Error\ContractGrammar;
use Kumwe\Producer\Error\Diagnostic;
use Kumwe\Producer\Error\DiagnosticLocation;
use Kumwe\Producer\Error\HostError;
use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Error\MessageReference;

/**
 * One request envelope after {@see parse()} accepted it — the only way an
 * instance comes to exist, so holding one is proof the body passed every
 * envelope check.
 *
 * What acceptance guarantees: the body was inside the byte bound, valid
 * UTF-8, well-formed JSON with no duplicate object member anywhere
 * (escape-spelled duplicates included), exactly the schema's members, each
 * context member inside its contract grammar, the wire version equal to
 * the supported pin, the operation inside the closed registry, and any
 * argument proven to fit the contract jsonValue shape. Everything else —
 * route agreement, revision and idempotency applicability, authorization —
 * is {@see Dispatcher}'s to enforce.
 *
 * @since   0.1.0
 */
final class RequestEnvelope
{
    /**
     * The negotiated wire version at the pinned Studio release.
     *
     * @since   0.1.0
     */
    public const WIRE_PROTOCOL_VERSION = '0.1.0-draft.2';

    /**
     * The contract fixes member and item counts but no byte bound, so the
     * body bound is host policy. The default fails closed at 1 MiB; a host
     * that accepts larger artifacts raises it explicitly at the call site.
     *
     * @since   0.1.0
     */
    public const DEFAULT_MAXIMUM_BODY_BYTES = 1048576;

    /**
     * The only members host-request.schema.json permits at the top level;
     * anything else is refused as an unknown member.
     *
     * @since   0.1.0
     */
    private const TOP_LEVEL_MEMBERS = ['arguments', 'context'];

    /**
     * The context members the schema requires; a missing one refuses the
     * envelope.
     *
     * @since   0.1.0
     */
    private const CONTEXT_REQUIRED_MEMBERS = [
        'operationId',
        'protocolVersion',
        'requestId',
        'resourceContextKey',
        'sessionGeneration',
    ];

    /**
     * The context members the schema permits but does not require; any
     * other name is refused as an unknown member.
     *
     * @since   0.1.0
     */
    private const CONTEXT_OPTIONAL_MEMBERS = [
        'expectedRevision',
        'idempotencyKey',
        'locale',
        'traceContext',
    ];

    /**
     * Reachable only from {@see parse()}, which has already proven every
     * member; the constructor binds the accepted state verbatim.
     *
     * @param   bool            $hasArguments  Whether the body carried an
     *                                         `arguments` member at all.
     * @param   mixed           $arguments     The decoded argument, already
     *                                         jsonValue-proven; null when
     *                                         absent.
     * @param   RequestContext  $context       The validated context members.
     *
     * @since   0.1.0
     */
    private function __construct(
        private readonly bool $hasArguments,
        private readonly mixed $arguments,
        private readonly RequestContext $context,
    ) {
    }

    /**
     * The validating path from raw body bytes to an accepted envelope, in
     * the deliberate order the class contract states: the byte bound
     * before decoding, UTF-8 and well-formedness before the duplicate
     * scan, structure and grammar before the version and registry checks.
     *
     * @param   string  $body              The raw request body bytes.
     * @param   int     $maximumBodyBytes  The host's body bound in bytes;
     *                                     must be positive.
     *
     * @return  self  The accepted envelope.
     *
     * @throws  HostRefusal                limit-exceeded, invalid-request, or incompatible
     * @throws  \InvalidArgumentException  When the caller passes a
     *                                     non-positive byte bound — a host
     *                                     configuration defect, not a
     *                                     request defect.
     *
     * @since   0.1.0
     */
    public static function parse(string $body, int $maximumBodyBytes = self::DEFAULT_MAXIMUM_BODY_BYTES): self
    {
        if ($maximumBodyBytes < 1) {
            throw new \InvalidArgumentException('The request body bound must be a positive byte count.');
        }
        if (strlen($body) > $maximumBodyBytes) {
            throw new HostRefusal(HostError::limitExceeded(new MessageReference(
                'kumwe.producer/request-too-large',
                'The request body exceeds the size this host accepts.'
            )));
        }
        if (!mb_check_encoding($body, 'UTF-8')) {
            self::refuse('kumwe.producer/request-not-utf8', 'The request body is not valid UTF-8 text.');
        }

        try {
            $document = CanonicalJson::decode($body);
        } catch (\JsonException) {
            self::refuse('kumwe.producer/malformed-json', 'The request body is not well-formed JSON.');
        }

        self::refuseDuplicateMembers($body);

        if (!$document instanceof \stdClass) {
            self::refuse(
                'kumwe.producer/malformed-envelope',
                'The request body must be a JSON object carrying the request envelope.'
            );
        }

        $members = get_object_vars($document);
        foreach ($members as $name => $value) {
            $name = (string) $name;
            if (!in_array($name, self::TOP_LEVEL_MEMBERS, true)) {
                self::refuseMember('unknown-member', $name);
            }
        }
        if (!property_exists($document, 'context')) {
            self::refuseMember('missing-member', 'context');
        }

        $context = self::parseContext($document->context);

        $hasArguments = property_exists($document, 'arguments');
        $arguments = $hasArguments ? $document->arguments : null;
        if ($hasArguments) {
            try {
                JsonValueGuard::assert($arguments);
            } catch (JsonShapeViolation $violation) {
                self::refuseArguments($violation);
            }
        }

        return new self($hasArguments, $arguments, $context);
    }

    /**
     * Whether the request carried an `arguments` member; explicit null is
     * an argument, absence is not.
     *
     * @return  bool  True when the member was present, even as null.
     *
     * @since   0.1.0
     */
    public function hasArguments(): bool
    {
        return $this->hasArguments;
    }

    /**
     * The decoded operation argument, already proven to fit the contract
     * jsonValue shape.
     *
     * @return  mixed  The argument, or null — consult
     *                 {@see hasArguments()} to tell an explicit null from
     *                 absence.
     *
     * @since   0.1.0
     */
    public function arguments(): mixed
    {
        return $this->arguments;
    }

    /**
     * The validated context members of the envelope.
     *
     * @return  RequestContext  Every member proven against its contract
     *                          grammar.
     *
     * @since   0.1.0
     */
    public function context(): RequestContext
    {
        return $this->context;
    }

    /**
     * Validates the `context` object: exactly the schema's members, each
     * against its contract grammar, then the wire version (a mismatch is
     * incompatible, before any work), then registry membership of the
     * operation (an unknown one is invalid-request).
     *
     * @param   mixed  $rawContext  The decoded `context` member as
     *                              received.
     *
     * @return  RequestContext  The validated context.
     *
     * @throws  HostRefusal  invalid-request or incompatible.
     *
     * @since   0.1.0
     */
    private static function parseContext(mixed $rawContext): RequestContext
    {
        if (!$rawContext instanceof \stdClass) {
            self::refuseMember('invalid-member', 'context');
        }
        $members = [];
        foreach (get_object_vars($rawContext) as $name => $value) {
            $members[(string) $name] = $value;
        }
        foreach ($members as $name => $value) {
            if (
                !in_array($name, self::CONTEXT_REQUIRED_MEMBERS, true)
                && !in_array($name, self::CONTEXT_OPTIONAL_MEMBERS, true)
            ) {
                self::refuseMember('unknown-member', $name);
            }
        }
        foreach (self::CONTEXT_REQUIRED_MEMBERS as $name) {
            if (!array_key_exists($name, $members)) {
                self::refuseMember('missing-member', $name);
            }
        }

        $protocolVersion = $members['protocolVersion'];
        if (!is_string($protocolVersion) || !ContractGrammar::isSemanticVersion($protocolVersion)) {
            self::refuseMember('invalid-member', 'protocolVersion');
        }

        $operationId = $members['operationId'];
        if (!is_string($operationId) || !ContractGrammar::isQualifiedName($operationId)) {
            self::refuseMember('invalid-member', 'operationId');
        }

        $requestId = $members['requestId'];
        if (!is_string($requestId) || !ContractGrammar::isStableId($requestId)) {
            self::refuseMember('invalid-member', 'requestId');
        }

        $resourceContextKey = $members['resourceContextKey'];
        if (!is_string($resourceContextKey) || !ContractGrammar::isStableId($resourceContextKey)) {
            self::refuseMember('invalid-member', 'resourceContextKey');
        }

        $sessionGeneration = $members['sessionGeneration'];
        if (!is_string($sessionGeneration) || !ContractGrammar::isRevision($sessionGeneration)) {
            self::refuseMember('invalid-member', 'sessionGeneration');
        }

        $expectedRevision = null;
        if (array_key_exists('expectedRevision', $members)) {
            $candidate = $members['expectedRevision'];
            if (!is_string($candidate) || !ContractGrammar::isRevision($candidate)) {
                self::refuseMember('invalid-member', 'expectedRevision');
            }
            $expectedRevision = $candidate;
        }

        $idempotencyKey = null;
        if (array_key_exists('idempotencyKey', $members)) {
            $candidate = $members['idempotencyKey'];
            if (!is_string($candidate) || !ContractGrammar::isStableId($candidate)) {
                self::refuseMember('invalid-member', 'idempotencyKey');
            }
            $idempotencyKey = $candidate;
        }

        $locale = null;
        if (array_key_exists('locale', $members)) {
            $candidate = $members['locale'];
            if (!is_string($candidate) || !ContractGrammar::isLocale($candidate)) {
                self::refuseMember('invalid-member', 'locale');
            }
            $locale = $candidate;
        }

        $traceContext = [];
        if (array_key_exists('traceContext', $members)) {
            $traceContext = self::parseTraceContext($members['traceContext']);
        }

        // Structure holds; now refuse a wire version this producer does not
        // speak — incompatible before any work, per the request schema.
        if ($protocolVersion !== self::WIRE_PROTOCOL_VERSION) {
            throw new HostRefusal(HostError::incompatible(
                new MessageReference(
                    'kumwe.producer/protocol-version-unsupported',
                    'The request names a wire protocol version this producer does not support.'
                ),
                [new Diagnostic(
                    'kumwe.producer/protocol-version-unsupported',
                    'error',
                    new MessageReference(
                        'kumwe.producer/protocol-version-unsupported',
                        'The request names a wire protocol version this producer does not support.'
                    ),
                    null,
                    ['supported' => self::WIRE_PROTOCOL_VERSION]
                )]
            ));
        }

        // The operation vocabulary is closed by the pinned registry.
        if (!OperationRegistry::isCapability($operationId)) {
            self::refuse(
                'kumwe.producer/unknown-operation',
                'The requested operation is not in the pinned Studio operation registry.'
            );
        }

        return new RequestContext(
            $operationId,
            $protocolVersion,
            $requestId,
            $resourceContextKey,
            $sessionGeneration,
            $expectedRevision,
            $idempotencyKey,
            $locale,
            $traceContext
        );
    }

    /**
     * Validates the optional `traceContext` object: at most 10 entries,
     * each name a contract local name, each value bounded UTF-8 text of at
     * most 200 code points.
     *
     * @param   mixed  $rawTraceContext  The decoded member as received.
     *
     * @return  array<string, string>  The validated tracing entries.
     *
     * @throws  HostRefusal  invalid-request when any bound or grammar
     *                       breaks.
     *
     * @since   0.1.0
     */
    private static function parseTraceContext(mixed $rawTraceContext): array
    {
        if (!$rawTraceContext instanceof \stdClass) {
            self::refuseMember('invalid-member', 'traceContext');
        }
        $entries = get_object_vars($rawTraceContext);
        if (count($entries) > 10) {
            self::refuseMember('invalid-member', 'traceContext');
        }
        $traceContext = [];
        foreach ($entries as $name => $value) {
            $name = (string) $name;
            if (!ContractGrammar::isLocalName($name)) {
                self::refuseMember('invalid-member', 'traceContext');
            }
            if (!is_string($value) || !ContractGrammar::isBoundedText($value, 0, 200)) {
                self::refuseMember('invalid-member', 'traceContext');
            }
            $traceContext[$name] = $value;
        }

        return $traceContext;
    }

    /**
     * Refuse any object in the accepted text that repeats a member name.
     *
     * Runs only on text json_decode has already accepted, so string tokens
     * and nesting are known well-formed and the scan is a plain linear
     * walk. Names compare after escape decoding, exactly as they collide
     * in a decoder.
     *
     * @param   string  $json  The accepted request text to scan.
     *
     * @throws  HostRefusal  invalid-request naming the repeated member in
     *                       a bounded diagnostic.
     *
     * @since   0.1.0
     */
    private static function refuseDuplicateMembers(string $json): void
    {
        $length = strlen($json);
        $offset = 0;
        /** @var list<string> $kinds */
        $kinds = [];
        /** @var list<array<string, true>> $seen */
        $seen = [];
        $depth = -1;

        while ($offset < $length) {
            $byte = $json[$offset];
            if ($byte === '"') {
                $start = $offset;
                $offset++;
                while ($offset < $length) {
                    $inner = $json[$offset];
                    if ($inner === '\\') {
                        $offset += 2;
                        continue;
                    }
                    if ($inner === '"') {
                        break;
                    }
                    $offset++;
                }
                $token = substr($json, $start, $offset - $start + 1);
                $offset++;

                if ($depth >= 0 && $kinds[$depth] === 'object') {
                    $next = $offset;
                    while ($next < $length && strspn($json, " \t\r\n", $next, 1) === 1) {
                        $next++;
                    }
                    if ($next < $length && $json[$next] === ':') {
                        $name = json_decode($token);
                        if (!is_string($name)) {
                            self::refuse(
                                'kumwe.producer/malformed-json',
                                'The request body is not well-formed JSON.'
                            );
                        }
                        if (isset($seen[$depth][$name])) {
                            self::refuse(
                                'kumwe.producer/duplicate-member',
                                'A JSON object in the request repeats a member name.',
                                [new Diagnostic(
                                    'kumwe.producer/duplicate-member',
                                    'error',
                                    new MessageReference(
                                        'kumwe.producer/duplicate-member',
                                        'A JSON object in the request repeats a member name.'
                                    ),
                                    null,
                                    ['member' => mb_substr($name, 0, 200, 'UTF-8')]
                                )]
                            );
                        }
                        $seen[$depth][$name] = true;
                    }
                }
                continue;
            }
            if ($byte === '{') {
                $depth++;
                $kinds[$depth] = 'object';
                $seen[$depth] = [];
            } elseif ($byte === '[') {
                $depth++;
                $kinds[$depth] = 'array';
                $seen[$depth] = [];
            } elseif ($byte === '}' || $byte === ']') {
                array_pop($kinds);
                array_pop($seen);
                $depth--;
            }
            $offset++;
        }
    }

    /**
     * The one refusal path of the parser: a fixed invalid-request whose
     * message is a catalog reference, never echoed request text.
     *
     * @param   string            $key             The catalog key of the
     *                                             refusal message.
     * @param   string            $defaultMessage  Its pre-written fallback.
     * @param   list<Diagnostic>  $diagnostics     Bounded pointers at the
     *                                             offending structure.
     *
     * @throws  HostRefusal  Always — invalid-request.
     *
     * @since   0.1.0
     */
    private static function refuse(string $key, string $defaultMessage, array $diagnostics = []): never
    {
        throw new HostRefusal(HostError::invalidRequest(
            new MessageReference($key, $defaultMessage),
            $diagnostics
        ));
    }

    /**
     * Refuses a structural member problem as malformed-envelope, naming
     * the member (truncated to its grammar bound) in a diagnostic
     * parameter rather than in the message.
     *
     * @param   string  $problem  One of unknown-member, missing-member, or
     *                            invalid-member.
     * @param   string  $member   The member name concerned.
     *
     * @throws  HostRefusal  Always — invalid-request.
     *
     * @since   0.1.0
     */
    private static function refuseMember(string $problem, string $member): never
    {
        $code = 'kumwe.producer/' . $problem;
        $messages = [
            'unknown-member' => 'The request carries a member the host request schema does not define.',
            'missing-member' => 'The request omits a member the host request schema requires.',
            'invalid-member' => 'A request member does not match the shape the host request schema requires.',
        ];
        $defaultMessage = $messages[$problem];
        self::refuse(
            'kumwe.producer/malformed-envelope',
            'The request envelope does not match the host request schema.',
            [new Diagnostic(
                $code,
                'error',
                new MessageReference($code, $defaultMessage),
                null,
                ['member' => mb_substr($member, 0, 200, 'UTF-8')]
            )]
        );
    }

    /**
     * Refuses an argument that broke the jsonValue shape as
     * malformed-envelope, carrying the violation's stable reason and its
     * JSON Pointer (under `/arguments`) — never the offending value.
     *
     * @param   JsonShapeViolation  $violation  The guard's typed refusal.
     *
     * @throws  HostRefusal  Always — invalid-request.
     *
     * @since   0.1.0
     */
    private static function refuseArguments(JsonShapeViolation $violation): never
    {
        $pointer = '/arguments' . $violation->pointer();
        $location = ContractGrammar::isBoundedText($pointer, 0, 1000)
            ? new DiagnosticLocation(null, null, null, $pointer)
            : null;
        self::refuse(
            'kumwe.producer/malformed-envelope',
            'The request envelope does not match the host request schema.',
            [new Diagnostic(
                'kumwe.producer/invalid-argument',
                'error',
                new MessageReference(
                    'kumwe.producer/invalid-argument',
                    'The operation argument does not fit the contract JSON value shape.'
                ),
                $location,
                ['reason' => $violation->reason()]
            )]
        );
    }
}
