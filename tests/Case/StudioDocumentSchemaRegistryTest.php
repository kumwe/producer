<?php

/**
 * Prove the exact Studio document registry, its sealed compiler and the
 * pinned positive and hostile document corpus.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Schema\SchemaInstanceDiagnostic;
use Kumwe\Producer\Schema\StudioDocumentSchemaRegistry;
use Kumwe\Producer\Schema\StudioDocumentValidation;
use Kumwe\Producer\Tests\TestCase;

final class StudioDocumentSchemaRegistryTest extends TestCase
{
    public function testTheExactRegistryValidatesEveryRuntimeDocumentKind(): void
    {
        $registry = StudioDocumentSchemaRegistry::fromVendoredCorpus();
        $this->assertSame(
            $registry,
            StudioDocumentSchemaRegistry::fromVendoredCorpus(),
            'The verified corpus must compile once per process.'
        );
        $this->assertSame(
            [
                'block-definition', 'pattern', 'field-adapter', 'inspector',
                'design-vocabulary', 'migration',
            ],
            StudioDocumentSchemaRegistry::CONTRIBUTION_KINDS,
            'Manifest schema 6 must use the exact six canonical contribution kinds.'
        );
        $this->assertSame(
            [
                'block-definition', 'pattern', 'field-adapter', 'inspector',
                'design-vocabulary', 'migration', 'blueprint', 'content-model',
                'entry', 'host-error', 'host-request', 'host-result', 'preview-message',
            ],
            StudioDocumentSchemaRegistry::DOCUMENT_KINDS,
            'The public document set must remain explicit and closed.'
        );

        $kinds = array_fill_keys(StudioDocumentSchemaRegistry::DOCUMENT_KINDS, true);
        $fixtures = glob(self::testkitRoot() . '/fixtures/*.json') ?: [];
        sort($fixtures);
        $validated = [];
        foreach ($fixtures as $file) {
            $document = CanonicalJson::decode((string) file_get_contents($file));
            $kind = $document instanceof \stdClass ? ($document->kind ?? null) : null;
            if (!is_string($kind) || !isset($kinds[$kind])) {
                continue;
            }
            $result = $registry->validate($kind, $document);
            $this->assertTrue($result->valid(), basename($file) . ' must satisfy its pinned document schema.');
            $this->assertSame([], $result->diagnostics(), 'A passing document must carry no diagnostics.');
            $validated[] = basename($file);
        }
        $this->assertSame(12, count($validated), 'Every released runtime-document fixture must be exercised.');

        $request = CanonicalJson::decode(<<<'JSON'
            {
                "context": {
                    "operationId": "studio.operation/artifact.load",
                    "protocolVersion": "0.1.0-draft.2",
                    "requestId": "requests/document-registry-1",
                    "resourceContextKey": "contexts/document-registry",
                    "sessionGeneration": "session-r1"
                },
                "arguments": {"id": "org.example.blueprints/product"}
            }
            JSON);
        $result = $registry->validate('host-request', $request);
        $this->assertTrue($result->valid(), 'A canonical host request must pass its cross-document closure.');
        $result = $registry->validate('host-result', CanonicalJson::decode('{"value":null,"revision":"r2"}'));
        $this->assertTrue($result->valid(), 'A canonical host result must pass its common-schema references.');
    }

    public function testTheCompleteRelevantHostileCorpusIsRefusedDeterministically(): void
    {
        $registry = StudioDocumentSchemaRegistry::fromVendoredCorpus();
        $kinds = array_fill_keys(StudioDocumentSchemaRegistry::DOCUMENT_KINDS, true);
        $files = glob(self::testkitRoot() . '/invalid/*.json') ?: [];
        sort($files);
        $validated = 0;
        foreach ($files as $file) {
            $vector = CanonicalJson::decode((string) file_get_contents($file));
            $schema = $vector instanceof \stdClass ? ($vector->schema ?? null) : null;
            $kind = is_string($schema) && str_ends_with($schema, '.schema.json')
                ? substr($schema, 0, -strlen('.schema.json'))
                : null;
            if (!is_string($kind) || !isset($kinds[$kind])) {
                continue;
            }
            $validated++;
            $first = $registry->validate($kind, $vector->value ?? null);
            $this->assertSame(false, $first->valid(), basename($file) . ' must be refused.');
            $this->assertTrue($first->diagnostics() !== [], basename($file) . ' must explain its refusal.');
            $second = $registry->validate($kind, $vector->value ?? null);
            $this->assertSame(
                self::diagnosticTuples($first->diagnostics()),
                self::diagnosticTuples($second->diagnostics()),
                basename($file) . ' diagnostics must be deterministic across fresh runs.'
            );
        }
        $this->assertSame(23, $validated, 'Every hostile fixture for the exposed document kinds must run.');
    }

    public function testUnknownKindsAndInconsistentVerdictsAreRefused(): void
    {
        $error = $this->assertThrows(
            static fn () => StudioDocumentSchemaRegistry::fromVendoredCorpus()->validate('media-asset', new \stdClass()),
            \LogicException::class,
            'A schema outside the closed runtime set must be refused.'
        );
        $this->assertStringContains(
            'not a supported canonical Studio document kind',
            $error->getMessage(),
            'The unknown-kind refusal must name the programming error.'
        );

        $this->assertThrows(
            static fn () => new StudioDocumentValidation(true, [
                new SchemaInstanceDiagnostic('', 'type', 'must be object'),
            ]),
            \InvalidArgumentException::class,
            'A successful verdict cannot carry diagnostics.'
        );
        $this->assertThrows(
            static fn () => new StudioDocumentValidation(false, []),
            \InvalidArgumentException::class,
            'A failed verdict cannot hide every diagnostic.'
        );
    }

    public function testTheSealedCompilerRefusesEverySchemaGraphEscape(): void
    {
        $cases = [
            'duplicate identity' => [
                static function (array $documents): array {
                    $migration = $documents['migration'] ?? null;
                    $inspector = $documents['inspector'] ?? null;
                    $identity = $inspector instanceof \stdClass ? ($inspector->{'$id'} ?? null) : null;
                    if (!$migration instanceof \stdClass || !is_string($identity)) {
                        throw new \LogicException('The exact test schema identities disappeared.');
                    }
                    $migration->{'$id'} = $identity;

                    return $documents;
                },
                'declares https://schemas.kumwe.org/studio/v1/inspector.schema.json more than once',
            ],
            'unsupported keyword' => [
                self::replaceMigration(['format' => 'uri']),
                'does not support',
            ],
            'nested identity' => [
                self::replaceMigration(['properties' => ['x' => ['$id' => 'https://attacker.test/x']]]),
                'must be the document root identity',
            ],
            'foreign dialect' => [
                self::replaceMigration([], 'https://json-schema.org/draft-07/schema'),
                'must declare JSON Schema Draft 2020-12',
            ],
            'sparse schema array' => [
                self::replaceMigration(['allOf' => []]),
                'must be a dense, non-empty array of schemas',
            ],
            'invalid type operand' => [
                self::replaceMigration(['type' => 'text']),
                'has an invalid operand for type',
            ],
            'unbounded pattern' => [
                self::replaceMigration(['pattern' => str_repeat('a', 501)]),
                'at most 500 UTF-8 characters',
            ],
            'broken pattern' => [
                self::replaceMigration(['pattern' => '(unclosed']),
                'is not a valid bounded Unicode regular expression',
            ],
            'escaping reference' => [
                self::replaceMigration(['$ref' => '../outside.schema.json']),
                'must stay within the schema registry root',
            ],
            'foreign reference' => [
                self::replaceMigration(['$ref' => 'https://attacker.test/schema.json']),
                'which is not in the pinned registry',
            ],
            'plain-name fragment' => [
                self::replaceMigration(['$ref' => '#anchor']),
                'must use a JSON Pointer fragment',
            ],
            'broken pointer escape' => [
                self::replaceMigration(['$ref' => '#/$defs/a~2b']),
                'is not a valid JSON Pointer reference',
            ],
            'non-schema target' => [
                self::replaceMigration(['title' => 'Migration', '$ref' => '#/title']),
                'does not reference a schema position',
            ],
        ];

        foreach ($cases as $label => [$poison, $message]) {
            $error = $this->assertThrows(
                static fn () => self::compile(self::applyPoison($poison, self::documents())),
                \RuntimeException::class,
                'The ' . $label . ' schema graph must be refused.'
            );
            $this->assertStringContains(
                $message,
                $error->getMessage(),
                'The ' . $label . ' graph must fail at its own closed arm.'
            );
        }

        $documents = self::documents();
        $shared = CanonicalJson::decode('{"type":"string"}');
        $replacement = self::minimalMigration();
        $replacement->properties = new \stdClass();
        $replacement->properties->a = $shared;
        $replacement->properties->b = $shared;
        $documents['migration'] = $replacement;
        $error = $this->assertThrows(
            static fn () => self::compile($documents),
            \RuntimeException::class,
            'A reused schema object must be refused.'
        );
        $this->assertStringContains('reuses or cycles', $error->getMessage(), 'Schema aliasing must fail closed.');
    }

    public function testReviewedPatternsTranslateAndRuntimeErrorsFailClosed(): void
    {
        $documents = self::documents();
        $documents['migration'] = self::document([
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'x' => ['type' => 'string', 'pattern' => '^[^\\u0000-\\u001F]*$'],
                'y' => ['type' => 'string', 'pattern' => '^\\\\u0041$'],
                'z' => ['type' => 'string', 'pattern' => '^\\u{1F600}$'],
                'bounded' => ['type' => 'string', 'pattern' => '^(a+)+$'],
            ],
        ]);
        $registry = self::compile($documents);

        $this->assertTrue(
            $registry->validate('migration', CanonicalJson::decode('{"x":"plain"}'))->valid(),
            'A translated Unicode range must admit plain text.'
        );
        $this->assertSame(
            false,
            $registry->validate('migration', (object) ['x' => "a\u{0001}b"])->valid(),
            'A translated Unicode range must refuse its control character.'
        );
        $this->assertTrue(
            $registry->validate('migration', (object) ['y' => '\\u0041'])->valid(),
            'An even backslash run must remain literal text.'
        );
        $this->assertSame(
            false,
            $registry->validate('migration', (object) ['y' => 'A'])->valid(),
            'An even backslash run must not become a Unicode escape.'
        );
        $this->assertTrue(
            $registry->validate('migration', (object) ['z' => "\u{1F600}"])->valid(),
            'A braced ECMAScript Unicode escape must translate exactly.'
        );

        $failed = $registry->validate('migration', (object) [
            'bounded' => str_repeat('a', 20000) . '!',
        ]);
        $this->assertSame(false, $failed->valid(), 'A bounded-pattern runtime refusal must fail closed.');
        $this->assertSame(
            'pattern',
            $failed->diagnostics()[0]->keyword ?? null,
            'Every PCRE refusal must remain a pattern diagnostic.'
        );
        $badUtf8 = $registry->validate('migration', (object) ['x' => "\xff"]);
        $this->assertSame(false, $badUtf8->valid(), 'Invalid UTF-8 must fail before pattern evaluation.');
        $this->assertSame(
            'canonical',
            $badUtf8->diagnostics()[0]->keyword ?? null,
            'Invalid UTF-8 must name the canonical document boundary.'
        );
    }

    public function testNonCanonicalPhpShapesFailAsValidationResults(): void
    {
        $registry = StudioDocumentSchemaRegistry::fromVendoredCorpus();
        $deep = null;
        for ($depth = 0; $depth <= CanonicalJson::DEFAULT_MAXIMUM_DEPTH; $depth++) {
            $deep = [$deep];
        }
        $documents = [
            'associative PHP array' => (object) ['value' => ['name' => 'not-a-decoded-object']],
            'invalid UTF-8' => (object) ['value' => "bad\xff"],
            'non-finite number' => (object) ['value' => NAN],
            'foreign object' => (object) ['value' => new \DateTimeImmutable()],
            'excessive depth' => (object) ['value' => $deep],
            'unsafe integer' => (object) ['value' => 9007199254740992],
        ];
        foreach ($documents as $label => $document) {
            $result = $registry->validate('host-result', $document);
            $this->assertSame(false, $result->valid(), 'The ' . $label . ' shape must be refused.');
            $this->assertSame(
                'canonical',
                $result->diagnostics()[0]->keyword ?? null,
                'The ' . $label . ' refusal must name the canonical boundary.'
            );
        }
    }

    public function testOversizedScalarsAndContainersFailInBoundedPreflight(): void
    {
        $registry = StudioDocumentSchemaRegistry::fromVendoredCorpus();

        $scalar = $registry->validate('host-result', str_repeat('x', 8388608));
        $this->assertSame(false, $scalar->valid(), 'An oversized scalar must be refused.');
        $this->assertSame(
            'canonical',
            $scalar->diagnostics()[0]->keyword ?? null,
            'The oversized scalar must fail at the canonical boundary.'
        );
        $this->assertStringContains(
            'byte limit',
            $scalar->diagnostics()[0]->message ?? '',
            'The scalar must stop at the pre-serialization byte budget.'
        );

        $container = $registry->validate('host-result', array_fill(0, 250000, null));
        $this->assertSame(false, $container->valid(), 'An excessive value graph must be refused.');
        $this->assertSame(
            'canonical',
            $container->diagnostics()[0]->keyword ?? null,
            'The excessive graph must fail at the canonical boundary.'
        );
        $this->assertStringContains(
            'node limit',
            $container->diagnostics()[0]->message ?? '',
            'The container must stop at the pre-serialization node budget.'
        );
    }

    public function testUniqueItemsScalesToTheReleasedBoundaryAndKeepsNumericEquality(): void
    {
        $documents = self::documents();
        $documents['migration'] = self::document([
            'type' => 'array',
            'maxItems' => 100000,
            'uniqueItems' => true,
        ]);
        $registry = self::compile($documents);

        $distinct = $registry->validate('migration', range(0, 99999));
        $this->assertTrue(
            $distinct->valid(),
            'One hundred thousand distinct members must complete without pairwise comparison.'
        );

        $duplicate = $registry->validate('migration', [1, 1.0]);
        $this->assertSame(false, $duplicate->valid(), 'JSON-equivalent numeric forms must duplicate.');
        $this->assertSame(
            'uniqueItems',
            $duplicate->diagnostics()[0]->keyword ?? null,
            'The numeric duplicate must retain the uniqueItems diagnostic.'
        );
        $this->assertStringContains(
            'items ## 0 and 1',
            $duplicate->diagnostics()[0]->message ?? '',
            'The linear index must preserve the first duplicate pair.'
        );
    }

    public function testLongStringsValidateWithoutBecomingMemoKeys(): void
    {
        $documents = self::documents();
        $documents['migration'] = self::document([
            'type' => 'string',
            'maxLength' => 1048576,
            'allOf' => [
                ['type' => 'string'],
                ['type' => 'string'],
                ['type' => 'string'],
            ],
        ]);
        $registry = self::compile($documents);
        $value = str_repeat('x', 1048576);
        $this->assertTrue($registry->validate('migration', $value)->valid(), 'A bounded long string must validate.');

        $identity = new \ReflectionMethod(StudioDocumentSchemaRegistry::class, 'instanceKey');
        $this->assertSame(
            null,
            $identity->invoke(null, $value),
            'A long scalar must never be copied into repeated schema memo keys.'
        );
    }

    public function testLargeJsonNumbersRemainExactlyDistinct(): void
    {
        $documents = self::documents();
        $documents['migration'] = self::document([
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'constant' => ['const' => 9007199254740991],
                'choice' => ['enum' => [9007199254740991]],
                'unique' => ['type' => 'array', 'uniqueItems' => true],
            ],
        ]);
        $registry = self::compile($documents);
        $result = $registry->validate('migration', (object) [
            'constant' => 9007199254740990,
            'choice' => 9007199254740990,
            'unique' => [9007199254740990, 9007199254740991],
        ]);
        $this->assertSame(false, $result->valid(), 'Adjacent safe integers must not satisfy const or enum.');
        $keywords = array_map(
            static fn (SchemaInstanceDiagnostic $diagnostic): string => $diagnostic->keyword,
            $result->diagnostics()
        );
        $this->assertTrue(in_array('const', $keywords, true), 'The exact constant mismatch must be retained.');
        $this->assertTrue(in_array('enum', $keywords, true), 'The exact enum mismatch must be retained.');
        $this->assertSame(
            false,
            in_array('uniqueItems', $keywords, true),
            'Adjacent safe integers must remain unique.'
        );

        $duplicate = $registry->validate('migration', (object) [
            'unique' => [9007199254740991, 9007199254740991.0],
        ]);
        $this->assertSame(false, $duplicate->valid(), 'Equivalent int and float values must still duplicate.');
        $this->assertSame(
            'uniqueItems',
            $duplicate->diagnostics()[0]->keyword ?? null,
            'Numeric-equivalent duplicates must name uniqueItems.'
        );

        $equality = new \ReflectionMethod(StudioDocumentSchemaRegistry::class, 'deepEqual');
        $this->assertSame(
            false,
            $equality->invoke(null, 9007199254740992, 9007199254740993),
            'Even caller-built integers above 2^53 must never collapse through a float cast.'
        );
    }

    /**
     * Load all exact schema documents by manifest basename.
     *
     * @return array<string, \stdClass>
     */
    private static function documents(): array
    {
        $root = dirname(__DIR__, 2) . '/resources/studio-contract/protocol/schemas';
        $manifest = CanonicalJson::decode((string) file_get_contents($root . '/manifest.json'));
        $entries = $manifest instanceof \stdClass ? ($manifest->schemas ?? null) : null;
        if (!is_array($entries) || !array_is_list($entries)) {
            throw new \LogicException('The exact test schema manifest has no entries.');
        }
        $documents = [];
        foreach ($entries as $entry) {
            $file = $entry instanceof \stdClass ? ($entry->file ?? null) : null;
            if (!is_string($file) || !str_ends_with($file, '.schema.json')) {
                throw new \LogicException('The exact test schema manifest carries a malformed entry.');
            }
            $document = CanonicalJson::decode(
                (string) file_get_contents($root . '/' . $file)
            );
            if (!$document instanceof \stdClass) {
                throw new \LogicException('An exact test schema did not decode as an object.');
            }
            $documents[substr($file, 0, -strlen('.schema.json'))] = $document;
        }

        return $documents;
    }

    /**
     * Apply and narrow one hostile schema mutation.
     *
     * @param mixed                         $poison    Candidate mutation callable.
     * @param array<string, \stdClass>      $documents Exact decoded schema graph.
     *
     * @return array<string, \stdClass>
     */
    private static function applyPoison(mixed $poison, array $documents): array
    {
        if (!is_callable($poison)) {
            throw new \LogicException('A hostile schema mutation is not callable.');
        }
        $mutated = $poison($documents);
        if (!is_array($mutated)) {
            throw new \LogicException('A hostile schema mutation did not return a document map.');
        }
        $narrowed = [];
        foreach ($mutated as $name => $document) {
            if (!is_string($name) || !$document instanceof \stdClass) {
                throw new \LogicException('A hostile schema mutation returned a malformed document map.');
            }
            $narrowed[$name] = $document;
        }

        return $narrowed;
    }

    /**
     * Invoke the sealed private compiler for hostile package tests.
     *
     * @param array<string, \stdClass> $documents Decoded schema graph.
     */
    private static function compile(array $documents): StudioDocumentSchemaRegistry
    {
        $reflection = new \ReflectionClass(StudioDocumentSchemaRegistry::class);
        $registry = $reflection->newInstanceWithoutConstructor();
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            throw new \LogicException('The registry compiler constructor disappeared.');
        }
        $constructor->invoke($registry, $documents);

        return $registry;
    }

    /**
     * Build a corpus mutation replacing only the migration schema.
     *
     * @param array<string, mixed> $members Extra root members.
     */
    private static function replaceMigration(
        array $members,
        string $dialect = 'https://json-schema.org/draft/2020-12/schema'
    ): callable {
        return static function (array $documents) use ($members, $dialect): array {
            $documents['migration'] = self::document($members, $dialect);

            return $documents;
        };
    }

    /**
     * Build one minimal migration schema with extra root members.
     *
     * @param array<string, mixed> $members Extra root members.
     */
    private static function document(
        array $members,
        string $dialect = 'https://json-schema.org/draft/2020-12/schema'
    ): \stdClass {
        $document = array_merge([
            '$id' => 'https://schemas.kumwe.org/studio/v1/migration.schema.json',
            '$schema' => $dialect,
            'type' => 'object',
        ], $members);
        $decoded = CanonicalJson::decode((string) json_encode($document, JSON_THROW_ON_ERROR));
        if (!$decoded instanceof \stdClass) {
            throw new \LogicException('A test migration schema did not decode as an object.');
        }

        return $decoded;
    }

    /**
     * Build the smallest exact-identity migration schema.
     */
    private static function minimalMigration(): \stdClass
    {
        return self::document([]);
    }

    /**
     * Convert diagnostics to stable value tuples.
     *
     * @param list<SchemaInstanceDiagnostic> $diagnostics Diagnostics to compare.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private static function diagnosticTuples(array $diagnostics): array
    {
        return array_map(
            static fn (SchemaInstanceDiagnostic $diagnostic): array => [
                $diagnostic->instancePath,
                $diagnostic->keyword,
                $diagnostic->message,
            ],
            $diagnostics
        );
    }

    /**
     * Exact vendored testkit root for package-owned tests.
     */
    private static function testkitRoot(): string
    {
        return dirname(__DIR__, 2) . '/resources/studio-contract/testkit';
    }
}
