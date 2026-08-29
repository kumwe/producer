<?php

declare(strict_types=1);

namespace Kumwe\Producer\Schema;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Canonical\CanonicalEncodingException;

/**
 * Exact, host-neutral validator registry for Producer's pinned Studio
 * document schemas.
 *
 * Construction begins at the coordinated release pin, verifies the released
 * schema manifest and all 47 manifested schema bytes, admits the closed
 * interpreter grammar, compiles the reviewed lexical patterns, and resolves
 * every local or cross-document reference without network access. Only the
 * published document kinds can be validated. Callers cannot inject roots,
 * references, patterns, custom directories or compatibility aliases.
 *
 * The contributed-property profile remains a separate trust boundary:
 * {@see SchemaPropertyProfile::admit()} continues to admit only its smaller,
 * format-free and pattern-free schema language. This registry interprets
 * `pattern` and root `$id` only because their exact source bytes are bound by
 * the vendored coordinated release.
 *
 * @since   0.2.0
 */
final class StudioDocumentSchemaRegistry
{
    /**
     * The six canonical composition-document kinds admitted by extension
     * manifest schema 6.
     *
     * @var list<string>
     *
     * @since   0.2.0
     */
    public const CONTRIBUTION_KINDS = [
        'block-definition',
        'pattern',
        'field-adapter',
        'inspector',
        'design-vocabulary',
        'migration',
    ];

    /**
     * The complete closed set of canonical Studio documents Producer admits
     * for runtime consumers: the six extension contributions, persisted
     * composition/model documents, host envelopes, and preview messages.
     *
     * @var list<string>
     *
     * @since   0.2.0
     */
    public const DOCUMENT_KINDS = [
        'block-definition',
        'pattern',
        'field-adapter',
        'inspector',
        'design-vocabulary',
        'migration',
        'blueprint',
        'content-model',
        'entry',
        'host-error',
        'host-request',
        'host-result',
        'preview-message',
    ];

    /**
     * The only JSON Schema dialect carried by the pinned corpus.
     *
     * @since   0.2.0
     */
    private const DRAFT_2020_12 = 'https://json-schema.org/draft/2020-12/schema';

    /**
     * Maximum bytes read for any individual manifest or schema document.
     *
     * @since   0.2.0
     */
    private const MAX_DOCUMENT_BYTES = 1048576;

    /**
     * Maximum canonical bytes admitted for one runtime document.
     *
     * This is a pre-schema resource ceiling. Individual pinned schemas apply
     * their smaller semantic limits after the canonical shape is proven.
     *
     * @since   0.2.0
     */
    private const MAX_INSTANCE_BYTES = 8388608;

    /**
     * Maximum scalar and container positions admitted before schema work.
     *
     * The byte ceiling alone would let a caller force millions of tiny PHP
     * values through canonical sorting and serialization. This independent
     * graph bound keeps that pre-schema work finite.
     *
     * @since   0.2.0
     */
    private const MAX_INSTANCE_NODES = 250000;

    /**
     * Largest integer magnitude interoperable with Studio's ECMAScript
     * canonical number model.
     *
     * @since   0.2.0
     */
    private const MAXIMUM_SAFE_INTEGER = 9007199254740991;

    /**
     * Maximum source characters in an admitted cross-document reference.
     *
     * @since   0.2.0
     */
    private const MAX_REFERENCE_LENGTH = 500;

    /**
     * Maximum source characters in an exact, reviewed lexical pattern.
     *
     * @since   0.2.0
     */
    private const MAX_PATTERN_LENGTH = 500;

    /**
     * Per-match PCRE work ceiling for a reviewed lexical pattern.
     *
     * @since   0.2.0
     */
    private const PATTERN_MATCH_LIMIT = 100000;

    /**
     * Per-match PCRE recursion/depth ceiling for a reviewed lexical pattern.
     *
     * @since   0.2.0
     */
    private const PATTERN_DEPTH_LIMIT = 1000;

    /**
     * The exact corpus interpreter's closed keyword set.
     *
     * It is the contributed property profile plus the two affordances present
     * only in release-reviewed schemas: document-root `$id` and `pattern`.
     *
     * @var array<string, true>
     *
     * @since   0.2.0
     */
    private const SUPPORTED_KEYWORDS = [
        '$defs' => true, '$id' => true, '$ref' => true, '$schema' => true,
        'additionalProperties' => true, 'allOf' => true, 'anyOf' => true,
        'const' => true, 'default' => true, 'dependentRequired' => true,
        'description' => true, 'else' => true, 'enum' => true, 'examples' => true,
        'exclusiveMaximum' => true, 'exclusiveMinimum' => true, 'if' => true,
        'items' => true, 'maxItems' => true, 'maxLength' => true,
        'maxProperties' => true, 'maximum' => true, 'minItems' => true,
        'minLength' => true, 'minProperties' => true, 'minimum' => true,
        'multipleOf' => true, 'not' => true, 'oneOf' => true, 'pattern' => true,
        'prefixItems' => true, 'properties' => true, 'propertyNames' => true,
        'readOnly' => true, 'required' => true, 'then' => true, 'title' => true,
        'type' => true, 'uniqueItems' => true, 'writeOnly' => true,
    ];

    /**
     * Document roots by admitted runtime kind.
     *
     * @var array<string, \stdClass>
     *
     * @since   0.2.0
     */
    private array $roots = [];

    /**
     * Resolved local and cross-document target per `$ref`-bearing schema node.
     *
     * @var \SplObjectStorage<\stdClass, \stdClass|bool>
     *
     * @since   0.2.0
     */
    private \SplObjectStorage $references;

    /**
     * Bounded compiled PCRE per exact `pattern`-bearing schema node.
     *
     * @var \SplObjectStorage<\stdClass, string>
     *
     * @since   0.2.0
     */
    private \SplObjectStorage $patterns;

    /**
     * Per-validation memo of schema verdicts at identity-bearing instances.
     *
     * @var \SplObjectStorage<\stdClass, array<string, array{0: bool, 1: list<SchemaInstanceDiagnostic>}>>
     *
     * @since   0.2.0
     */
    private \SplObjectStorage $memo;

    /**
     * Compile one already decoded, manifest-verified corpus.
     *
     * @param array<string, \stdClass> $documents Schema name to decoded root.
     *
     * @since   0.2.0
     */
    private function __construct(array $documents)
    {
        /** @var \SplObjectStorage<\stdClass, \stdClass|bool> $references */
        $references = new \SplObjectStorage();
        /** @var \SplObjectStorage<\stdClass, string> $patterns */
        $patterns = new \SplObjectStorage();
        $this->references = $references;
        $this->patterns = $patterns;
        $this->memo = new \SplObjectStorage();

        $byUri = [];
        $pointers = [];
        foreach ($documents as $name => $document) {
            try {
                CanonicalJson::stringify($document, CanonicalJson::DEFAULT_MAXIMUM_DEPTH);
            } catch (CanonicalEncodingException $error) {
                throw new \RuntimeException(
                    sprintf('Registry schema document %s is not canonical JSON.', $name),
                    0,
                    $error
                );
            }
            $baseUri = $document->{'$id'} ?? null;
            if (!is_string($baseUri) || $baseUri === '') {
                throw new \RuntimeException(sprintf(
                    'Registry schema document %s must declare a root $id.',
                    $name
                ));
            }
            if (isset($byUri[$baseUri])) {
                throw new \RuntimeException(sprintf('Schema registry declares %s more than once.', $baseUri));
            }
            $byUri[$baseUri] = $document;
            $pointers[$baseUri] = [];
        }

        $sites = [];
        foreach ($documents as $name => $document) {
            $baseUri = $document->{'$id'};
            if (!is_string($baseUri)) {
                throw new \LogicException('A checked Studio schema identity lost its string shape.');
            }
            $this->walkDocument($document, $baseUri, $pointers[$baseUri], $sites);
            if (in_array($name, self::DOCUMENT_KINDS, true)) {
                $this->roots[$name] = $document;
            }
        }
        foreach (self::DOCUMENT_KINDS as $kind) {
            if (!isset($this->roots[$kind])) {
                throw new \RuntimeException(sprintf(
                    'The pinned Studio schema registry has no %s document.',
                    $kind
                ));
            }
        }

        foreach ($sites as [$node, $baseUri, $pointer, $reference]) {
            $this->references[$node] = self::resolveSite(
                $baseUri,
                $pointer,
                $reference,
                $byUri,
                $pointers
            );
        }
    }

    /**
     * Load, verify and compile Producer's Composer-installed Studio schemas.
     *
     * The shared instance is safe because every validation resets all
     * per-document interpreter state and returns its own immutable result.
     *
     * @throws \RuntimeException When installed contract bytes, manifest identities or schema
     *                           grammar differ from the coordinated release.
     *
     * @since   0.2.0
     */
    public static function fromVendoredCorpus(): self
    {
        /** @var self|null $shared */
        static $shared = null;

        if ($shared === null) {
            StudioContractResources::releaseRecord();
            StudioContractResources::testkitManifestBytes();
            $shared = new self(self::loadPinnedDocuments(self::contractRoot()));
        }

        return $shared;
    }

    /**
     * Validate one decoded canonical document against its exact pinned schema.
     *
     * The value shape is the canonical JSON shape: null, bool, int, float,
     * string, list array or stdClass. A wrong root shape is a normal schema
     * refusal, not a PHP type error.
     *
     * @param string $kind     One of {@see self::DOCUMENT_KINDS}.
     * @param mixed  $document Decoded canonical document.
     *
     * @throws \LogicException When the kind is outside the closed document set or interpreter
     *                         invariants are violated.
     *
     * @since   0.2.0
     */
    public function validate(string $kind, mixed $document): StudioDocumentValidation
    {
        $root = $this->roots[$kind] ?? null;
        if ($root === null) {
            throw new \LogicException(sprintf(
                '"%s" is not a supported canonical Studio document kind.',
                $kind
            ));
        }

        $preflight = self::preflightCanonicalInput($document);
        if ($preflight !== null) {
            return new StudioDocumentValidation(false, [
                new SchemaInstanceDiagnostic('', 'canonical', $preflight),
            ]);
        }

        try {
            $canonical = CanonicalJson::stringify($document, CanonicalJson::DEFAULT_MAXIMUM_DEPTH);
        } catch (CanonicalEncodingException $error) {
            return new StudioDocumentValidation(false, [
                new SchemaInstanceDiagnostic('', 'canonical', $error->getMessage()),
            ]);
        }
        if (strlen($canonical) > self::MAX_INSTANCE_BYTES) {
            return new StudioDocumentValidation(false, [
                new SchemaInstanceDiagnostic(
                    '',
                    'canonical',
                    'Canonical Studio document exceeds the registry byte limit.'
                ),
            ]);
        }

        $this->memo = new \SplObjectStorage();
        $errors = [];
        /** @var \SplObjectStorage<object, mixed> $active */
        $active = new \SplObjectStorage();
        $valid = $this->subschema($root, $document, '', $errors, $active);
        $diagnostics = self::uniqueDiagnostics($errors);
        if ($valid === ($diagnostics !== [])) {
            throw new \LogicException('Studio document verdict and diagnostics disagree.');
        }

        return new StudioDocumentValidation($valid, $diagnostics);
    }

    /**
     * Prove a caller-built value has bounded canonical shape before the
     * serializer allocates its complete output or member-ordering buffers.
     *
     * The traversal computes the exact serialized byte cost without sorting
     * or constructing output. CanonicalJson remains the final authority after
     * the bound is established, including its forbidden-member rule.
     *
     * @param mixed $document Candidate decoded document.
     *
     * @return string|null Refusal detail, or null when bounded for serialization.
     *
     * @since   0.2.0
     */
    private static function preflightCanonicalInput(mixed $document): ?string
    {
        $remaining = self::MAX_INSTANCE_BYTES;
        $nodes = 0;

        return self::preflightCanonicalValue($document, 0, $nodes, $remaining);
    }

    /**
     * Consume one canonical value from the fixed node and exact byte budgets.
     *
     * @param mixed $value     Value at this position.
     * @param int   $depth     Containers already entered above this value.
     * @param int   $nodes     Positions visited so far.
     * @param int   $remaining Canonical bytes still available.
     *
     * @return string|null Refusal detail, or null while both budgets hold.
     *
     * @since   0.2.0
     */
    private static function preflightCanonicalValue(
        mixed $value,
        int $depth,
        int &$nodes,
        int &$remaining
    ): ?string {
        if (++$nodes > self::MAX_INSTANCE_NODES) {
            return 'Canonical Studio document exceeds the registry node limit.';
        }
        if ($value === null) {
            return self::consumeCanonicalBytes($remaining, 4);
        }
        if (is_bool($value)) {
            return self::consumeCanonicalBytes($remaining, $value ? 4 : 5);
        }
        if (is_int($value)) {
            if ($value > self::MAXIMUM_SAFE_INTEGER || $value < -self::MAXIMUM_SAFE_INTEGER) {
                return 'Canonical JSON integers must stay inside the interoperable safe range.';
            }

            return self::consumeCanonicalBytes($remaining, strlen((string) $value));
        }
        if (is_float($value)) {
            if (!is_finite($value) || abs($value) > self::MAXIMUM_SAFE_INTEGER) {
                return 'Canonical JSON numbers must be finite and stay inside the interoperable safe range.';
            }

            return self::consumeCanonicalBytes($remaining, strlen(CanonicalJson::stringify($value)));
        }
        if (is_string($value)) {
            return self::consumeCanonicalString($value, $remaining);
        }
        if ($depth >= CanonicalJson::DEFAULT_MAXIMUM_DEPTH) {
            return 'Canonical Studio document exceeds the registry depth limit.';
        }
        if (is_array($value)) {
            if (count($value) > self::MAX_INSTANCE_NODES - $nodes) {
                return 'Canonical Studio document exceeds the registry node limit.';
            }
            if (!array_is_list($value)) {
                return 'Canonical JSON arrays must be lists; decode objects as stdClass.';
            }
            $failure = self::consumeCanonicalBytes($remaining, 2);
            if ($failure !== null) {
                return $failure;
            }
            $first = true;
            foreach ($value as $item) {
                if (!$first && ($failure = self::consumeCanonicalBytes($remaining, 1)) !== null) {
                    return $failure;
                }
                $first = false;
                $failure = self::preflightCanonicalValue($item, $depth + 1, $nodes, $remaining);
                if ($failure !== null) {
                    return $failure;
                }
            }

            return null;
        }
        if ($value instanceof \stdClass) {
            $failure = self::consumeCanonicalBytes($remaining, 2);
            if ($failure !== null) {
                return $failure;
            }
            $first = true;
            $members = get_object_vars($value);
            if (count($members) > self::MAX_INSTANCE_NODES - $nodes) {
                return 'Canonical Studio document exceeds the registry node limit.';
            }
            foreach ($members as $member => $memberValue) {
                if (!$first && ($failure = self::consumeCanonicalBytes($remaining, 1)) !== null) {
                    return $failure;
                }
                $first = false;
                $failure = self::consumeCanonicalString($member, $remaining);
                if ($failure !== null) {
                    return $failure;
                }
                $failure = self::consumeCanonicalBytes($remaining, 1);
                if ($failure !== null) {
                    return $failure;
                }
                $failure = self::preflightCanonicalValue($memberValue, $depth + 1, $nodes, $remaining);
                if ($failure !== null) {
                    return $failure;
                }
            }

            return null;
        }

        return 'Canonical JSON cannot represent a ' . get_debug_type($value) . ' value.';
    }

    /**
     * Consume the exact canonical quoted-string byte cost without allocating
     * an escaped copy.
     *
     * @param string $value     Candidate UTF-8 string or member name.
     * @param int    $remaining Canonical bytes still available.
     *
     * @return string|null Refusal detail, or null when the string fits.
     *
     * @since   0.2.0
     */
    private static function consumeCanonicalString(string $value, int &$remaining): ?string
    {
        $length = strlen($value);
        if ($length > $remaining - 2) {
            return 'Canonical Studio document exceeds the registry byte limit.';
        }
        if (!mb_check_encoding($value, 'UTF-8')) {
            return 'Canonical JSON strings and member names must be valid UTF-8.';
        }

        $cost = 2;
        for ($index = 0; $index < $length; $index++) {
            $byte = ord($value[$index]);
            if ($byte === 0x22
                || $byte === 0x5c
                || $byte === 0x08
                || $byte === 0x09
                || $byte === 0x0a
                || $byte === 0x0c
                || $byte === 0x0d
            ) {
                $cost += 2;
            } elseif ($byte < 0x20) {
                $cost += 6;
            } else {
                $cost++;
            }
            if ($cost > $remaining) {
                return 'Canonical Studio document exceeds the registry byte limit.';
            }
        }

        $remaining -= $cost;

        return null;
    }

    /**
     * Consume a fixed number of bytes from the canonical-output budget.
     *
     * @param int $remaining Canonical bytes still available.
     * @param int $bytes     Non-negative fixed byte cost.
     *
     * @return string|null Refusal detail, or null when the bytes fit.
     *
     * @since   0.2.0
     */
    private static function consumeCanonicalBytes(int &$remaining, int $bytes): ?string
    {
        if ($bytes > $remaining) {
            return 'Canonical Studio document exceeds the registry byte limit.';
        }
        $remaining -= $bytes;

        return null;
    }

    /**
     * Resolve the package's exact Studio contract root without making it a
     * consumer-configurable trust input.
     *
     * @since   0.2.0
     */
    private static function contractRoot(): string
    {
        $expected = dirname(__DIR__, 2) . '/resources/studio-contract';
        $resolved = realpath($expected);
        if ($resolved === false || !is_dir($expected) || is_link($expected) || $resolved !== $expected) {
            throw new \RuntimeException('The installed Studio contract root is missing or linked.');
        }

        return $resolved;
    }

    /**
     * Verify PIN, schema manifest, exact directory membership, all schema
     * digests and identities before handing decoded documents to the compiler.
     *
     * @param string $contractRoot Absolute Studio contract root.
     *
     * @return array<string, \stdClass> Schema basename to decoded root.
     *
     * @since   0.2.0
     */
    private static function loadPinnedDocuments(string $contractRoot): array
    {
        $pinPath = $contractRoot . '/PIN.json';
        $pin = self::decodeObject(self::fileBytes($pinPath), $pinPath);
        $files = $pin->files ?? null;
        if (!is_array($files) || !array_is_list($files)) {
            throw new \RuntimeException('The installed Studio PIN has no ordered files list.');
        }

        $manifestRelative = 'protocol/schemas/manifest.json';
        $manifestHex = null;
        foreach ($files as $entry) {
            if (!$entry instanceof \stdClass) {
                throw new \RuntimeException('The installed Studio PIN carries a malformed file entry.');
            }
            if (($entry->file ?? null) !== $manifestRelative) {
                continue;
            }
            if ($manifestHex !== null || !is_string($entry->sha256 ?? null)) {
                throw new \RuntimeException('The installed Studio PIN repeats or malforms its schema manifest.');
            }
            $manifestHex = $entry->sha256;
        }
        if (!is_string($manifestHex) || preg_match('/^[a-f0-9]{64}$/', $manifestHex) !== 1) {
            throw new \RuntimeException('The installed Studio PIN does not bind its schema manifest.');
        }

        $schemaDirectory = $contractRoot . '/protocol/schemas';
        $manifestPath = $contractRoot . '/' . $manifestRelative;
        $manifestBytes = self::fileBytes($manifestPath);
        if (!hash_equals($manifestHex, hash('sha256', $manifestBytes))) {
            throw new \RuntimeException('The installed Studio schema manifest differs from its release pin.');
        }
        $manifest = self::decodeObject($manifestBytes, $manifestPath);
        $epoch = $manifest->epoch ?? null;
        $entries = $manifest->schemas ?? null;
        if (($manifest->kind ?? null) !== 'schema-manifest'
            || !is_string($epoch)
            || !str_ends_with($epoch, '/')
            || !is_array($entries)
            || !array_is_list($entries)
            || $entries === []
        ) {
            throw new \RuntimeException('The installed Studio schema manifest is malformed.');
        }

        $documents = [];
        $ids = [];
        $expectedFiles = ['manifest.json' => true];
        foreach ($entries as $entry) {
            $file = $entry instanceof \stdClass ? ($entry->file ?? null) : null;
            $digest = $entry instanceof \stdClass ? ($entry->digest ?? null) : null;
            $id = $entry instanceof \stdClass ? ($entry->id ?? null) : null;
            if (!is_string($file)
                || preg_match('/^[a-z][a-z0-9-]*\.schema\.json$/', $file) !== 1
                || !is_string($digest)
                || !self::sha256Sri($digest)
                || !is_string($id)
                || $id !== $epoch . $file
                || isset($expectedFiles[$file])
                || isset($ids[$id])
            ) {
                throw new \RuntimeException('The installed Studio schema manifest carries a malformed entry.');
            }
            $expectedFiles[$file] = true;
            $ids[$id] = true;

            $path = $schemaDirectory . '/' . $file;
            $resolved = realpath($path);
            if ($resolved === false
                || !is_file($path)
                || is_link($path)
                || $resolved !== $path
                || !str_starts_with($resolved, $schemaDirectory . '/')
            ) {
                throw new \RuntimeException('A manifested Studio schema is missing or leaves its package root.');
            }
            $bytes = self::fileBytes($path);
            $actual = 'sha256-' . base64_encode(hash('sha256', $bytes, true));
            if (!hash_equals($digest, $actual)) {
                throw new \RuntimeException('A manifested Studio schema differs from its released digest: ' . $file);
            }
            $document = self::decodeObject($bytes, $path);
            if (($document->{'$id'} ?? null) !== $id) {
                throw new \RuntimeException('A manifested Studio schema declares the wrong $id: ' . $file);
            }
            $documents[substr($file, 0, -strlen('.schema.json'))] = $document;
        }

        $presentFiles = [];
        $iterator = new \FilesystemIterator($schemaDirectory, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                throw new \RuntimeException('The Studio schema directory yielded an invalid filesystem entry.');
            }
            if ($file->isLink() || !$file->isFile()) {
                throw new \RuntimeException('The Studio schema directory contains a linked or non-file entry.');
            }
            $presentFiles[$file->getFilename()] = true;
        }
        ksort($expectedFiles);
        ksort($presentFiles);
        if ($presentFiles !== $expectedFiles) {
            throw new \RuntimeException('The Studio schema directory differs from its exact released manifest.');
        }

        return $documents;
    }

    /**
     * Walk one document, admitting its closed grammar, recording every schema
     * position, compiling patterns and collecting reference sites.
     *
     * @param \stdClass                                                     $document Document root.
     * @param string                                                        $baseUri  Root `$id`.
     * @param array<string, \stdClass|bool>                                 $pointers Schema positions.
     * @param list<array{0: \stdClass, 1: string, 2: string, 3: string}>    $sites    Reference sites.
     *
     * @since   0.2.0
     */
    private function walkDocument(
        \stdClass $document,
        string $baseUri,
        array &$pointers,
        array &$sites
    ): void {
        $seen = new \SplObjectStorage();
        $nodes = 0;
        $walkSchema = function (mixed $value, string $pointer, int $depth) use (
            &$walkSchema,
            &$pointers,
            &$sites,
            &$nodes,
            $baseUri,
            $seen
        ): void {
            $location = $baseUri . '#' . $pointer;
            if ($depth > 64 || ++$nodes > 4096) {
                throw new \RuntimeException($location . ' exceeds the bounded Studio schema graph.');
            }
            if (is_bool($value)) {
                $pointers[$pointer] = $value;

                return;
            }
            if (!$value instanceof \stdClass) {
                throw new \RuntimeException($location . ' must be a plain JSON Schema object.');
            }
            if ($seen->offsetExists($value)) {
                throw new \RuntimeException($location . ' reuses or cycles a schema object.');
            }
            $seen->offsetSet($value, true);
            $pointers[$pointer] = $value;

            foreach (get_object_vars($value) as $keyword => $operand) {
                $keyword = (string) $keyword;
                $keywordLocation = $location . '/' . self::escapeToken($keyword);
                if (!isset(self::SUPPORTED_KEYWORDS[$keyword])) {
                    throw new \RuntimeException(sprintf(
                        '%s uses keyword "%s", which the Studio document interpreter does not support.',
                        $keywordLocation,
                        $keyword
                    ));
                }
                switch ($keyword) {
                    case '$id':
                        if ($pointer !== '' || $operand !== $baseUri) {
                            throw new \RuntimeException($keywordLocation . ' must be the document root identity.');
                        }
                        break;
                    case '$schema':
                        if ($operand !== self::DRAFT_2020_12) {
                            throw new \RuntimeException($keywordLocation . ' must declare JSON Schema Draft 2020-12.');
                        }
                        break;
                    case '$ref':
                        if (!is_string($operand)
                            || !mb_check_encoding($operand, 'UTF-8')
                            || mb_strlen($operand, 'UTF-8') > self::MAX_REFERENCE_LENGTH
                        ) {
                            throw new \RuntimeException($keywordLocation . ' must be a bounded UTF-8 reference.');
                        }
                        $sites[] = [$value, $baseUri, $pointer, $operand];
                        break;
                    case '$defs':
                    case 'properties':
                        if (!$operand instanceof \stdClass) {
                            throw new \RuntimeException($keywordLocation . ' must be an object of schemas.');
                        }
                        foreach (get_object_vars($operand) as $name => $member) {
                            $walkSchema(
                                $member,
                                $pointer . '/' . $keyword . '/' . self::escapeToken((string) $name),
                                $depth + 1
                            );
                        }
                        break;
                    case 'additionalProperties':
                    case 'else':
                    case 'if':
                    case 'items':
                    case 'not':
                    case 'propertyNames':
                    case 'then':
                        $walkSchema($operand, $pointer . '/' . $keyword, $depth + 1);
                        break;
                    case 'allOf':
                    case 'anyOf':
                    case 'oneOf':
                    case 'prefixItems':
                        if (!is_array($operand) || !array_is_list($operand) || $operand === []) {
                            throw new \RuntimeException(
                                $keywordLocation . ' must be a dense, non-empty array of schemas.'
                            );
                        }
                        foreach ($operand as $index => $member) {
                            $walkSchema($member, $pointer . '/' . $keyword . '/' . $index, $depth + 1);
                        }
                        break;
                    case 'pattern':
                        $this->patterns[$value] = self::compilePattern($operand, $keywordLocation);
                        break;
                    default:
                        self::assertKeywordOperand($keyword, $operand, $keywordLocation);
                        break;
                }
            }
        };
        $walkSchema($document, '', 1);
    }

    /**
     * Admit the non-schema-bearing keyword operands used by the exact corpus.
     *
     * @param string $keyword  Closed keyword name.
     * @param mixed  $operand  Decoded operand.
     * @param string $location Absolute schema location for a refusal.
     *
     * @since   0.2.0
     */
    private static function assertKeywordOperand(string $keyword, mixed $operand, string $location): void
    {
        $valid = match ($keyword) {
            'type' => self::validTypeOperand($operand),
            'required' => self::validUniqueStringList($operand, false),
            'dependentRequired' => self::validDependentRequired($operand),
            'enum' => is_array($operand) && array_is_list($operand) && $operand !== [],
            'examples' => is_array($operand) && array_is_list($operand),
            'description', 'title' => is_string($operand) && mb_check_encoding($operand, 'UTF-8'),
            'maxItems', 'maxLength', 'maxProperties', 'minItems', 'minLength', 'minProperties'
                => is_int($operand) && $operand >= 0,
            'exclusiveMaximum', 'exclusiveMinimum', 'maximum', 'minimum'
                => (is_int($operand) || is_float($operand)) && is_finite((float) $operand),
            'multipleOf' => (is_int($operand) || is_float($operand)) && is_finite((float) $operand)
                && $operand > 0,
            'readOnly', 'uniqueItems', 'writeOnly' => is_bool($operand),
            'const', 'default' => self::isJsonValue($operand),
            default => false,
        };
        if (!$valid) {
            throw new \RuntimeException($location . ' has an invalid operand for ' . $keyword . '.');
        }
    }

    /**
     * Whether a `type` operand is one unique type or a non-empty unique list.
     *
     * @param mixed $operand Candidate type operand.
     *
     * @since   0.2.0
     */
    private static function validTypeOperand(mixed $operand): bool
    {
        $types = ['array', 'boolean', 'integer', 'null', 'number', 'object', 'string'];
        if (is_string($operand)) {
            return in_array($operand, $types, true);
        }
        if (!is_array($operand) || !array_is_list($operand) || $operand === []) {
            return false;
        }
        $seen = [];
        foreach ($operand as $type) {
            if (!is_string($type) || !in_array($type, $types, true) || isset($seen[$type])) {
                return false;
            }
            $seen[$type] = true;
        }

        return true;
    }

    /**
     * Whether an operand is a unique list of strings.
     *
     * @param mixed $operand   Candidate list.
     * @param bool  $allowEmpty Whether the empty list is admitted.
     *
     * @since   0.2.0
     */
    private static function validUniqueStringList(mixed $operand, bool $allowEmpty): bool
    {
        if (!is_array($operand) || !array_is_list($operand) || (!$allowEmpty && $operand === [])) {
            return false;
        }
        $seen = [];
        foreach ($operand as $value) {
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || isset($seen[$value])) {
                return false;
            }
            $seen[$value] = true;
        }

        return true;
    }

    /**
     * Whether an operand is a closed map of unique required-name lists.
     *
     * @param mixed $operand Candidate dependentRequired operand.
     *
     * @since   0.2.0
     */
    private static function validDependentRequired(mixed $operand): bool
    {
        if (!$operand instanceof \stdClass) {
            return false;
        }
        foreach (get_object_vars($operand) as $required) {
            if (!self::validUniqueStringList($required, false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a decoded value stays inside the canonical JSON value model.
     *
     * @param mixed $value Candidate decoded JSON value.
     *
     * @since   0.2.0
     */
    private static function isJsonValue(mixed $value): bool
    {
        try {
            CanonicalJson::stringify($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Compile one release-reviewed ECMAScript lexical pattern as bounded
     * Unicode PCRE, translating only real Unicode escape sequences.
     *
     * @param mixed  $operand  Pattern source.
     * @param string $location Schema location for a refusal.
     *
     * @since   0.2.0
     */
    private static function compilePattern(mixed $operand, string $location): string
    {
        if (!is_string($operand)
            || !mb_check_encoding($operand, 'UTF-8')
            || mb_strlen($operand, 'UTF-8') > self::MAX_PATTERN_LENGTH
        ) {
            throw new \RuntimeException(sprintf(
                '%s must be a lexical pattern of at most %d UTF-8 characters.',
                $location,
                self::MAX_PATTERN_LENGTH
            ));
        }
        $translated = preg_replace_callback(
            '/(?<backslashes>\\\\+)u(?<code>\{[0-9A-Fa-f]{1,6}\}|[0-9A-Fa-f]{4})/',
            static function (array $match): string {
                $slashes = strlen($match['backslashes']);
                if ($slashes % 2 === 0) {
                    return $match[0];
                }

                return substr($match['backslashes'], 0, $slashes - 1)
                    . '\\x{' . trim($match['code'], '{}') . '}';
            },
            $operand
        );
        if (!is_string($translated)) {
            throw new \RuntimeException($location . ' could not be translated into a lexical pattern.');
        }
        $compiled = '~(*LIMIT_MATCH=' . self::PATTERN_MATCH_LIMIT . ')(*LIMIT_DEPTH='
            . self::PATTERN_DEPTH_LIMIT . ')' . str_replace('~', '\\~', $translated) . '~u';
        if (@preg_match($compiled, '') === false || preg_last_error() !== PREG_NO_ERROR) {
            throw new \RuntimeException($location . ' is not a valid bounded Unicode regular expression.');
        }

        return $compiled;
    }

    /**
     * Resolve one reference strictly inside the manifest-registered document
     * graph and onto a position the compiler identified as a schema.
     *
     * @param string                                          $baseUri   Referring document identity.
     * @param string                                          $pointer   Referring schema pointer.
     * @param string                                          $reference Raw `$ref`.
     * @param array<string, \stdClass>                       $byUri     Documents by identity.
     * @param array<string, array<string, \stdClass|bool>>   $pointers Schema positions by identity.
     *
     * @since   0.2.0
     */
    private static function resolveSite(
        string $baseUri,
        string $pointer,
        string $reference,
        array $byUri,
        array $pointers
    ): \stdClass|bool {
        $location = $baseUri . '#' . $pointer . '/$ref';
        $hashIndex = strpos($reference, '#');
        $uriPart = $hashIndex === false ? $reference : substr($reference, 0, $hashIndex);
        $fragment = $hashIndex === false ? '' : substr($reference, $hashIndex + 1);

        if ($uriPart === '') {
            $targetUri = $baseUri;
        } elseif (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $uriPart) === 1) {
            $targetUri = $uriPart;
        } else {
            if (str_starts_with($uriPart, '/')
                || str_contains($uriPart, '\\')
                || preg_match('~(?:^|/)\.\.?(?:/|$)~', $uriPart) === 1
            ) {
                throw new \RuntimeException($location . ' must stay within the schema registry root.');
            }
            $slash = strrpos($baseUri, '/');
            if ($slash === false) {
                throw new \RuntimeException($location . ' has no resolvable document base.');
            }
            $targetUri = substr($baseUri, 0, $slash + 1) . $uriPart;
        }
        if (!isset($byUri[$targetUri], $pointers[$targetUri])) {
            throw new \RuntimeException(sprintf(
                '%s references %s, which is not in the pinned registry.',
                $location,
                $targetUri
            ));
        }

        if ($fragment !== '' && !str_starts_with($fragment, '/')) {
            throw new \RuntimeException($location . ' must use a JSON Pointer fragment.');
        }
        $canonical = '';
        if ($fragment !== '') {
            foreach (explode('/', substr($fragment, 1)) as $token) {
                if (preg_match('/~(?![01])|~$/', $token) === 1) {
                    throw new \RuntimeException($location . ' is not a valid JSON Pointer reference.');
                }
                $decoded = str_replace(['~1', '~0'], ['/', '~'], $token);
                $canonical .= '/' . self::escapeToken($decoded);
            }
        }
        if (!array_key_exists($canonical, $pointers[$targetUri])) {
            throw new \RuntimeException($location . ' does not reference a schema position.');
        }

        return $pointers[$targetUri][$canonical];
    }

    /**
     * Decode JSON bytes as an object with a path-bearing installation error.
     *
     * @param string $bytes JSON document bytes.
     * @param string $path  Package path named by a refusal.
     *
     * @since   0.2.0
     */
    private static function decodeObject(string $bytes, string $path): \stdClass
    {
        try {
            $decoded = CanonicalJson::decode($bytes);
        } catch (\JsonException $error) {
            throw new \RuntimeException('The installed Studio JSON file is malformed: ' . $path, 0, $error);
        }
        if (!$decoded instanceof \stdClass) {
            throw new \RuntimeException('The installed Studio JSON file must contain an object: ' . $path);
        }

        return $decoded;
    }

    /**
     * Read one bounded, required, regular and unlinked package file.
     *
     * @param string $path Absolute package path.
     *
     * @since   0.2.0
     */
    private static function fileBytes(string $path): string
    {
        $pathStat = @lstat($path);
        if (!is_array($pathStat) || !self::regularFileStat($pathStat)) {
            throw new \RuntimeException('A required Studio package file is missing or linked: ' . $path);
        }
        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) {
            throw new \RuntimeException('A required Studio package file is unreadable: ' . $path);
        }
        try {
            $before = fstat($handle);
            if (!is_array($before)
                || !self::regularFileStat($before)
                || !self::sameFileIdentity($pathStat, $before)
                || $before['size'] > self::MAX_DOCUMENT_BYTES
            ) {
                throw new \RuntimeException('A Studio package file changed or exceeds its byte bound: ' . $path);
            }
            $bytes = stream_get_contents($handle, self::MAX_DOCUMENT_BYTES + 1);
            $after = fstat($handle);
            if (!is_string($bytes)
                || strlen($bytes) > self::MAX_DOCUMENT_BYTES
                || !is_array($after)
                || !self::sameFileSnapshot($before, $after)
            ) {
                throw new \RuntimeException('A Studio package file changed while it was read: ' . $path);
            }
        } finally {
            fclose($handle);
        }

        return $bytes;
    }

    /**
     * Whether a filesystem stat describes an ordinary file rather than a
     * link, device, directory or socket.
     *
     * @param array<int|string, int> $stat Filesystem stat.
     *
     * @since   0.2.0
     */
    private static function regularFileStat(array $stat): bool
    {
        return isset($stat['mode']) && ($stat['mode'] & 0170000) === 0100000;
    }

    /**
     * Whether a path stat and opened-handle stat name the same file identity.
     *
     * @param array<int|string, int> $pathStat Path stat.
     * @param array<int|string, int> $openStat Opened-handle stat.
     *
     * @since   0.2.0
     */
    private static function sameFileIdentity(array $pathStat, array $openStat): bool
    {
        return isset($pathStat['dev'], $pathStat['ino'], $openStat['dev'], $openStat['ino'])
            && $pathStat['dev'] === $openStat['dev']
            && $pathStat['ino'] === $openStat['ino'];
    }

    /**
     * Whether an opened file retained identity and content metadata across a
     * bounded read.
     *
     * @param array<int|string, int> $before Stat before reading.
     * @param array<int|string, int> $after  Stat after reading.
     *
     * @since   0.2.0
     */
    private static function sameFileSnapshot(array $before, array $after): bool
    {
        foreach (['dev', 'ino', 'size', 'mtime', 'ctime'] as $member) {
            if (!isset($before[$member], $after[$member]) || $before[$member] !== $after[$member]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a digest uses Studio's canonical SHA-256 SRI spelling.
     *
     * @param string $digest Candidate digest.
     *
     * @since   0.2.0
     */
    private static function sha256Sri(string $digest): bool
    {
        return preg_match('/^sha256-[A-Za-z0-9+\/]{42}[AEIMQUYcgkosw048]=$/', $digest) === 1;
    }

    /**
     * Validate one subschema and memoize identity-bearing instance verdicts.
     *
     * @param \stdClass|bool                   $schema   Subschema to apply.
     * @param mixed                            $instance Decoded instance at this location.
     * @param string                           $path     Instance JSON Pointer.
     * @param list<SchemaInstanceDiagnostic>   $errors   Ordered failure sink.
     * @param \SplObjectStorage<object, mixed> $active   Schema nodes on the current stack.
     *
     * @since   0.2.0
     */
    private function subschema(
        \stdClass|bool $schema,
        mixed $instance,
        string $path,
        array &$errors,
        \SplObjectStorage $active
    ): bool {
        if (is_bool($schema)) {
            if (!$schema) {
                $errors[] = new SchemaInstanceDiagnostic($path, 'false', 'boolean schema is false');
            }

            return $schema;
        }
        $memoKey = self::instanceKey($instance);
        $locationKey = $memoKey === null ? null : strlen($path) . ':' . $path . $memoKey;
        if ($locationKey !== null && isset($this->memo[$schema])) {
            $cached = $this->memo[$schema][$locationKey] ?? null;
            if ($cached !== null) {
                foreach ($cached[1] as $diagnostic) {
                    $errors[] = $diagnostic;
                }

                return $cached[0];
            }
        }
        if ($active->offsetExists($schema)) {
            throw new \LogicException('Studio schema evaluation cycled without consuming instance input.');
        }
        $active->offsetSet($schema, true);
        $firstNewError = count($errors);
        try {
            $valid = $this->node($schema, $instance, $path, $errors, $active);
        } finally {
            $active->offsetUnset($schema);
        }
        $diagnostics = self::uniqueDiagnostics(array_slice($errors, $firstNewError));
        if ($valid === ($diagnostics !== [])) {
            throw new \LogicException('Studio subschema verdict and diagnostics disagree.');
        }
        if ($locationKey !== null) {
            $memoized = isset($this->memo[$schema]) ? $this->memo[$schema] : [];
            $memoized[$locationKey] = [$valid, $diagnostics];
            $this->memo[$schema] = $memoized;
        }

        return $valid;
    }

    /**
     * Apply every assertion keyword declared by one admitted schema node.
     *
     * @param \stdClass                        $schema   Schema node.
     * @param mixed                            $instance Decoded instance at this location.
     * @param string                           $path     Instance JSON Pointer.
     * @param list<SchemaInstanceDiagnostic>   $errors   Ordered failure sink.
     * @param \SplObjectStorage<object, mixed> $active   Schema nodes on the current stack.
     *
     * @since   0.2.0
     */
    private function node(
        \stdClass $schema,
        mixed $instance,
        string $path,
        array &$errors,
        \SplObjectStorage $active
    ): bool {
        $valid = true;
        $fail = function (string $keyword, string $message, ?string $at = null) use (
            &$valid,
            &$errors,
            $path
        ): void {
            $valid = false;
            $errors[] = new SchemaInstanceDiagnostic($at ?? $path, $keyword, $message);
        };

        if (property_exists($schema, '$ref')) {
            $target = $this->references->offsetExists($schema) ? $this->references[$schema] : null;
            if ($target === null) {
                throw new \LogicException('A Studio schema reference was not resolved at compilation.');
            }
            if (!$this->subschema($target, $instance, $path, $errors, $active)) {
                $valid = false;
            }
        }

        $type = $schema->type ?? null;
        if (is_string($type)) {
            if (!self::matchesType($type, $instance)) {
                $fail('type', 'must be ' . $type);
            }
        } elseif (is_array($type)) {
            $matched = false;
            foreach ($type as $name) {
                if (is_string($name) && self::matchesType($name, $instance)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $names = array_map(
                    static fn (mixed $name): string => is_string($name) ? $name : '',
                    $type
                );
                $fail('type', 'must be ' . implode(',', $names));
            }
        }

        $enum = $schema->enum ?? null;
        if (is_array($enum)) {
            $matched = false;
            foreach ($enum as $member) {
                if (self::deepEqual($member, $instance)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $fail('enum', 'must be equal to one of the allowed values');
            }
        }
        if (property_exists($schema, 'const') && !self::deepEqual($schema->const, $instance)) {
            $fail('const', 'must be equal to constant');
        }

        $this->combinators($schema, $instance, $path, $errors, $active, $fail);

        if (is_string($instance)) {
            $this->stringKeywords($schema, $instance, $fail);
        } elseif ((is_int($instance) || is_float($instance)) && is_finite((float) $instance)) {
            self::numberKeywords($schema, $instance, $fail);
        } elseif (is_array($instance)) {
            if (!$this->arrayKeywords($schema, array_values($instance), $path, $errors, $fail)) {
                $valid = false;
            }
        } elseif ($instance instanceof \stdClass) {
            if (!$this->objectKeywords($schema, $instance, $path, $errors, $fail)) {
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * Apply composition keywords with silent scratch buffers for speculative
     * branches.
     *
     * @param \stdClass                                 $schema   Schema node.
     * @param mixed                                     $instance Decoded instance.
     * @param string                                    $path     Instance JSON Pointer.
     * @param list<SchemaInstanceDiagnostic>            $errors   Ordered failure sink.
     * @param \SplObjectStorage<object, mixed>          $active   Schema nodes on the stack.
     * @param callable(string, string, ?string=): void  $fail     Failure reporter.
     *
     * @since   0.2.0
     */
    private function combinators(
        \stdClass $schema,
        mixed $instance,
        string $path,
        array &$errors,
        \SplObjectStorage $active,
        callable $fail
    ): void {
        $speculate = function (mixed $subschema) use ($instance, $path, $active): bool {
            $scratch = [];

            return $this->subschema(self::asSubschema($subschema), $instance, $path, $scratch, $active);
        };

        $allOf = $schema->allOf ?? null;
        if (is_array($allOf)) {
            foreach ($allOf as $member) {
                if (!$this->subschema(self::asSubschema($member), $instance, $path, $errors, $active)) {
                    $fail('allOf', 'must match all schemas in allOf');
                }
            }
        }
        $anyOf = $schema->anyOf ?? null;
        if (is_array($anyOf)) {
            $matched = false;
            foreach ($anyOf as $member) {
                if ($speculate($member)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $fail('anyOf', 'must match a schema in anyOf');
            }
        }
        $oneOf = $schema->oneOf ?? null;
        if (is_array($oneOf)) {
            $matches = 0;
            foreach ($oneOf as $member) {
                if ($speculate($member) && ++$matches > 1) {
                    break;
                }
            }
            if ($matches !== 1) {
                $fail('oneOf', 'must match exactly one schema in oneOf');
            }
        }
        if (property_exists($schema, 'not') && $speculate($schema->not)) {
            $fail('not', 'must NOT be valid');
        }
        if (property_exists($schema, 'if')) {
            $branch = $speculate($schema->if) ? ($schema->then ?? null) : ($schema->else ?? null);
            if ($branch !== null
                && !$this->subschema(self::asSubschema($branch), $instance, $path, $errors, $active)
            ) {
                $fail('if', 'must match the conditional schema');
            }
        }
    }

    /**
     * Apply exact reviewed patterns and code-point string-length bounds.
     *
     * Any PCRE runtime refusal, including UTF-8, backtrack, recursion or JIT
     * errors, is a failed pattern assertion and never an accidental pass.
     *
     * @param \stdClass                                $schema   Schema node.
     * @param string                                   $instance String instance.
     * @param callable(string, string, ?string=): void $fail     Failure reporter.
     *
     * @since   0.2.0
     */
    private function stringKeywords(\stdClass $schema, string $instance, callable $fail): void
    {
        if (property_exists($schema, 'pattern')) {
            if (!is_string($schema->pattern) || !$this->patterns->offsetExists($schema)) {
                throw new \LogicException('A Studio lexical pattern was not compiled at registry admission.');
            }
            $match = @preg_match($this->patterns[$schema], $instance);
            if ($match !== 1 || preg_last_error() !== PREG_NO_ERROR) {
                $fail('pattern', sprintf('must match pattern "%s"', $schema->pattern));
            }
        }

        $minLength = $schema->minLength ?? null;
        $maxLength = $schema->maxLength ?? null;
        if (is_int($minLength) || is_int($maxLength)) {
            $length = mb_strlen($instance, 'UTF-8');
            if (is_int($minLength) && $length < $minLength) {
                $fail('minLength', sprintf('must NOT have fewer than %d characters', $minLength));
            }
            if (is_int($maxLength) && $length > $maxLength) {
                $fail('maxLength', sprintf('must NOT have more than %d characters', $maxLength));
            }
        }
    }

    /**
     * Apply numeric bounds and exact base-10 `multipleOf` comparison.
     *
     * @param \stdClass                                $schema   Schema node.
     * @param int|float                                $instance Finite number.
     * @param callable(string, string, ?string=): void $fail     Failure reporter.
     *
     * @since   0.2.0
     */
    private static function numberKeywords(\stdClass $schema, int|float $instance, callable $fail): void
    {
        $minimum = $schema->minimum ?? null;
        if ((is_int($minimum) || is_float($minimum)) && $instance < $minimum) {
            $fail('minimum', 'must be >= ' . self::encodeNumber($minimum));
        }
        $maximum = $schema->maximum ?? null;
        if ((is_int($maximum) || is_float($maximum)) && $instance > $maximum) {
            $fail('maximum', 'must be <= ' . self::encodeNumber($maximum));
        }
        $exclusiveMinimum = $schema->exclusiveMinimum ?? null;
        if ((is_int($exclusiveMinimum) || is_float($exclusiveMinimum)) && $instance <= $exclusiveMinimum) {
            $fail('exclusiveMinimum', 'must be > ' . self::encodeNumber($exclusiveMinimum));
        }
        $exclusiveMaximum = $schema->exclusiveMaximum ?? null;
        if ((is_int($exclusiveMaximum) || is_float($exclusiveMaximum)) && $instance >= $exclusiveMaximum) {
            $fail('exclusiveMaximum', 'must be < ' . self::encodeNumber($exclusiveMaximum));
        }
        $multipleOf = $schema->multipleOf ?? null;
        if ((is_int($multipleOf) || is_float($multipleOf))
            && !self::isCanonicalDecimalMultiple($instance, $multipleOf)
        ) {
            $fail('multipleOf', 'must be multiple of ' . self::encodeNumber($multipleOf));
        }
    }

    /**
     * Apply array item, cardinality and uniqueness assertions.
     *
     * @param \stdClass                                $schema   Schema node.
     * @param list<mixed>                              $instance Array instance.
     * @param string                                   $path     Instance JSON Pointer.
     * @param list<SchemaInstanceDiagnostic>           $errors   Ordered failure sink.
     * @param callable(string, string, ?string=): void $fail     Failure reporter.
     *
     * @since   0.2.0
     */
    private function arrayKeywords(
        \stdClass $schema,
        array $instance,
        string $path,
        array &$errors,
        callable $fail
    ): bool {
        $valid = true;
        $child = function (mixed $subschema, int $index) use (&$valid, $instance, $path, &$errors): void {
            $fresh = new \SplObjectStorage();
            if (!$this->subschema(
                self::asSubschema($subschema),
                $instance[$index],
                $path . '/' . $index,
                $errors,
                $fresh
            )) {
                $valid = false;
            }
        };

        $prefixItems = $schema->prefixItems ?? null;
        $prefixLength = is_array($prefixItems) ? count($prefixItems) : 0;
        if (is_array($prefixItems)) {
            $bounded = min($prefixLength, count($instance));
            for ($index = 0; $index < $bounded; $index++) {
                $child($prefixItems[$index], $index);
            }
        }
        if (property_exists($schema, 'items') && count($instance) > $prefixLength) {
            $items = $schema->items;
            if ($items === false) {
                $fail('items', sprintf('must NOT have more than %d items', $prefixLength));
            } elseif ($items !== true) {
                for ($index = $prefixLength; $index < count($instance); $index++) {
                    $child($items, $index);
                }
            }
        }

        $minItems = $schema->minItems ?? null;
        if (is_int($minItems) && count($instance) < $minItems) {
            $fail('minItems', sprintf('must NOT have fewer than %d items', $minItems));
        }
        $maxItems = $schema->maxItems ?? null;
        if (is_int($maxItems) && count($instance) > $maxItems) {
            $fail('maxItems', sprintf('must NOT have more than %d items', $maxItems));
        }
        if (($schema->uniqueItems ?? null) === true) {
            $duplicate = self::findDuplicateIndexes($instance);
            if ($duplicate !== null) {
                $fail('uniqueItems', sprintf(
                    'must NOT have duplicate items (items ## %d and %d are identical)',
                    $duplicate[0],
                    $duplicate[1]
                ));
            }
        }

        return $valid;
    }

    /**
     * Apply object members in UTF-16 code-unit order, including closed maps,
     * required names, property names and dependency assertions.
     *
     * @param \stdClass                                $schema   Schema node.
     * @param \stdClass                                $instance Object instance.
     * @param string                                   $path     Instance JSON Pointer.
     * @param list<SchemaInstanceDiagnostic>           $errors   Ordered failure sink.
     * @param callable(string, string, ?string=): void $fail     Failure reporter.
     *
     * @since   0.2.0
     */
    private function objectKeywords(
        \stdClass $schema,
        \stdClass $instance,
        string $path,
        array &$errors,
        callable $fail
    ): bool {
        $valid = true;
        $memberNames = CodeUnitOrder::sortedMemberNames($instance);

        $properties = $schema->properties ?? null;
        $properties = $properties instanceof \stdClass ? $properties : null;
        if ($properties !== null) {
            foreach (CodeUnitOrder::sortedMemberNames($properties) as $name) {
                if (!property_exists($instance, $name)) {
                    continue;
                }
                $fresh = new \SplObjectStorage();
                if (!$this->subschema(
                    self::asSubschema($properties->{$name}),
                    $instance->{$name},
                    $path . '/' . self::escapeToken($name),
                    $errors,
                    $fresh
                )) {
                    $valid = false;
                }
            }
        }

        $required = $schema->required ?? null;
        if (is_array($required)) {
            foreach (self::sortedStrings($required) as $name) {
                if (!property_exists($instance, $name)) {
                    $fail('required', sprintf("must have required property '%s'", $name));
                }
            }
        }

        if (property_exists($schema, 'additionalProperties')) {
            $additional = $schema->additionalProperties;
            foreach ($memberNames as $name) {
                if ($properties !== null && property_exists($properties, $name)) {
                    continue;
                }
                if ($additional === false) {
                    $fail('additionalProperties', 'must NOT have additional properties');
                } elseif ($additional !== true) {
                    $fresh = new \SplObjectStorage();
                    if (!$this->subschema(
                        self::asSubschema($additional),
                        $instance->{$name},
                        $path . '/' . self::escapeToken($name),
                        $errors,
                        $fresh
                    )) {
                        $valid = false;
                    }
                }
            }
        }

        if (property_exists($schema, 'propertyNames')) {
            foreach ($memberNames as $name) {
                $scratch = [];
                $fresh = new \SplObjectStorage();
                if (!$this->subschema(
                    self::asSubschema($schema->propertyNames),
                    $name,
                    $path,
                    $scratch,
                    $fresh
                )) {
                    $fail('propertyNames', sprintf("property name '%s' is invalid", $name));
                }
            }
        }

        $dependentRequired = $schema->dependentRequired ?? null;
        if ($dependentRequired instanceof \stdClass) {
            foreach (CodeUnitOrder::sortedMemberNames($dependentRequired) as $trigger) {
                $dependents = $dependentRequired->{$trigger};
                if (!property_exists($instance, $trigger) || !is_array($dependents)) {
                    continue;
                }
                foreach (self::sortedStrings($dependents) as $dependent) {
                    if (!property_exists($instance, $dependent)) {
                        $fail('dependentRequired', sprintf(
                            'must have property %s when property %s is present',
                            $dependent,
                            $trigger
                        ));
                    }
                }
            }
        }

        $minProperties = $schema->minProperties ?? null;
        if (is_int($minProperties) && count($memberNames) < $minProperties) {
            $fail('minProperties', sprintf('must NOT have fewer than %d properties', $minProperties));
        }
        $maxProperties = $schema->maxProperties ?? null;
        if (is_int($maxProperties) && count($memberNames) > $maxProperties) {
            $fail('maxProperties', sprintf('must NOT have more than %d properties', $maxProperties));
        }

        return $valid;
    }

    /**
     * Compare an instance and divisor through exact base-10 coefficients.
     *
     * @param int|float $instance Finite instance number.
     * @param int|float $divisor  Finite positive divisor.
     *
     * @since   0.2.0
     */
    private static function isCanonicalDecimalMultiple(int|float $instance, int|float $divisor): bool
    {
        [$valueDigits, $valueExponent] = self::canonicalDecimal($instance);
        [$divisorDigits, $divisorExponent] = self::canonicalDecimal($divisor);
        if ($valueDigits === '0') {
            return true;
        }
        $difference = $valueExponent - $divisorExponent;
        if ($difference >= 0) {
            return self::digitsModulo($valueDigits . str_repeat('0', $difference), $divisorDigits) === '0';
        }

        return self::digitsModulo($valueDigits, $divisorDigits . str_repeat('0', -$difference)) === '0';
    }

    /**
     * Split a canonical number into trailing-zero-free digits and exponent.
     *
     * @param int|float $value Finite number.
     *
     * @return array{0: string, 1: int}
     *
     * @since   0.2.0
     */
    private static function canonicalDecimal(int|float $value): array
    {
        $encoded = self::encodeNumber($value);
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?(?:[eE]([+-]?\d+))?$/', $encoded, $parts) !== 1) {
            throw new \LogicException('Canonical decimal conversion requires a finite number.');
        }
        $fraction = $parts[3] ?? '';
        $digits = ltrim($parts[2] . $fraction, '0');
        if ($digits === '') {
            return ['0', 0];
        }
        $exponent = (int) ($parts[4] ?? '0') - strlen($fraction);
        $stripped = rtrim($digits, '0');
        $exponent += strlen($digits) - strlen($stripped);

        return [$stripped, $exponent];
    }

    /**
     * Compute a decimal digit-string remainder without native integer bounds.
     *
     * @param string $dividend Unsigned decimal digits.
     * @param string $divisor  Non-zero unsigned decimal digits.
     *
     * @since   0.2.0
     */
    private static function digitsModulo(string $dividend, string $divisor): string
    {
        $remainder = '0';
        $length = strlen($dividend);
        for ($index = 0; $index < $length; $index++) {
            $remainder = ltrim($remainder . $dividend[$index], '0');
            if ($remainder === '') {
                $remainder = '0';
                continue;
            }
            while (self::digitsCompare($remainder, $divisor) >= 0) {
                $remainder = self::digitsSubtract($remainder, $divisor);
            }
        }

        return $remainder;
    }

    /**
     * Compare unsigned digit strings without leading zeros.
     *
     * @param string $left  Left digits.
     * @param string $right Right digits.
     *
     * @since   0.2.0
     */
    private static function digitsCompare(string $left, string $right): int
    {
        $byLength = strlen($left) <=> strlen($right);

        return $byLength !== 0 ? $byLength : strcmp($left, $right);
    }

    /**
     * Subtract one unsigned digit string from a larger or equal one.
     *
     * @param string $minuend    Left digits.
     * @param string $subtrahend Right digits.
     *
     * @since   0.2.0
     */
    private static function digitsSubtract(string $minuend, string $subtrahend): string
    {
        $subtrahend = str_pad($subtrahend, strlen($minuend), '0', STR_PAD_LEFT);
        $result = '';
        $borrow = 0;
        for ($index = strlen($minuend) - 1; $index >= 0; $index--) {
            $digit = (int) $minuend[$index] - (int) $subtrahend[$index] - $borrow;
            $borrow = $digit < 0 ? 1 : 0;
            $result = (string) ($digit + $borrow * 10) . $result;
        }
        $result = ltrim($result, '0');

        return $result === '' ? '0' : $result;
    }

    /**
     * Compare two decoded JSON values deeply, with numeric type equivalence.
     *
     * @param mixed $left  First value.
     * @param mixed $right Second value.
     *
     * @since   0.2.0
     */
    private static function deepEqual(mixed $left, mixed $right): bool
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            if ((is_float($left) && !is_finite($left)) || (is_float($right) && !is_finite($right))) {
                return false;
            }
            try {
                [$leftDigits, $leftExponent] = self::canonicalDecimal($left);
                [$rightDigits, $rightExponent] = self::canonicalDecimal($right);
            } catch (CanonicalEncodingException) {
                return false;
            }
            if ($leftDigits === '0' && $rightDigits === '0') {
                return true;
            }

            return ($left < 0) === ($right < 0)
                && $leftDigits === $rightDigits
                && $leftExponent === $rightExponent;
        }
        if ($left === $right) {
            return true;
        }
        if (is_array($left) && is_array($right)) {
            if (count($left) !== count($right)) {
                return false;
            }
            foreach ($left as $index => $value) {
                if (!array_key_exists($index, $right) || !self::deepEqual($value, $right[$index])) {
                    return false;
                }
            }

            return true;
        }
        if ($left instanceof \stdClass && $right instanceof \stdClass) {
            $leftMembers = get_object_vars($left);
            $rightMembers = get_object_vars($right);
            if (count($leftMembers) !== count($rightMembers)) {
                return false;
            }
            foreach ($leftMembers as $name => $value) {
                if (!array_key_exists($name, $rightMembers) || !self::deepEqual($value, $rightMembers[$name])) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Whether an instance matches one closed JSON Schema type name.
     *
     * @param string $name     Type name.
     * @param mixed  $instance Decoded instance.
     *
     * @since   0.2.0
     */
    private static function matchesType(string $name, mixed $instance): bool
    {
        return match ($name) {
            'array' => is_array($instance),
            'boolean' => is_bool($instance),
            'integer' => is_int($instance)
                || (is_float($instance) && is_finite($instance) && floor($instance) === $instance),
            'null' => $instance === null,
            'number' => is_int($instance) || (is_float($instance) && is_finite($instance)),
            'object' => $instance instanceof \stdClass,
            'string' => is_string($instance),
            default => false,
        };
    }

    /**
     * Find the first pair of deep-equal array members.
     *
     * @param list<mixed> $instance Array instance.
     *
     * @return array{0: int, 1: int}|null
     *
     * @since   0.2.0
     */
    private static function findDuplicateIndexes(array $instance): ?array
    {
        $seen = [];
        foreach ($instance as $index => $value) {
            $key = 'v' . CanonicalJson::stringify($value);
            if (isset($seen[$key])) {
                return [$seen[$key], $index];
            }
            $seen[$key] = $index;
        }

        return null;
    }

    /**
     * Collapse exact duplicate diagnostics while preserving evaluation order.
     *
     * @param list<SchemaInstanceDiagnostic> $errors Raw diagnostics.
     *
     * @return list<SchemaInstanceDiagnostic>
     *
     * @since   0.2.0
     */
    private static function uniqueDiagnostics(array $errors): array
    {
        $seen = [];
        $diagnostics = [];
        foreach ($errors as $error) {
            $key = $error->instancePath . "\u{1}" . $error->keyword . "\u{1}" . $error->message;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $diagnostics[] = $error;
        }

        return $diagnostics;
    }

    /**
     * Narrow a compiler-admitted subschema operand to its runtime type.
     *
     * @param mixed $value Admitted operand.
     *
     * @since   0.2.0
     */
    private static function asSubschema(mixed $value): \stdClass|bool
    {
        if (is_bool($value) || $value instanceof \stdClass) {
            return $value;
        }

        throw new \LogicException('A compiled Studio subschema operand lost its shape.');
    }

    /**
     * Keep decoded string operands sorted by UTF-16 code unit.
     *
     * @param array<int|string, mixed> $values Decoded operands.
     *
     * @return list<string>
     *
     * @since   0.2.0
     */
    private static function sortedStrings(array $values): array
    {
        $strings = [];
        foreach ($values as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        }
        usort($strings, CodeUnitOrder::compare(...));

        return $strings;
    }

    /**
     * Encode one finite number through Producer's canonical number grammar.
     *
     * @param int|float $value Finite number.
     *
     * @since   0.2.0
     */
    private static function encodeNumber(int|float $value): string
    {
        return CanonicalJson::stringify($value);
    }

    /**
     * Derive a collision-safe per-run memo identity when cheaply available.
     *
     * @param mixed $instance Decoded instance.
     *
     * @since   0.2.0
     */
    private static function instanceKey(mixed $instance): ?string
    {
        if ($instance instanceof \stdClass) {
            return 'o' . spl_object_id($instance);
        }
        if ($instance === null || is_bool($instance) || is_int($instance) || is_float($instance)) {
            return 's' . var_export($instance, true);
        }
        if (is_string($instance)) {
            if (strlen($instance) > 256) {
                return null;
            }

            return 't' . strlen($instance) . ':' . $instance;
        }

        return null;
    }

    /**
     * Escape one JSON Pointer token.
     *
     * @param string $token Raw token.
     *
     * @since   0.2.0
     */
    private static function escapeToken(string $token): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $token);
    }
}
