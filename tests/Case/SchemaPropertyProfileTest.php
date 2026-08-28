<?php

/**
 * Replay Studio's schema-profile conformance corpus and the hostile edges
 * the corpus leaves untaken.
 *
 * Every vendored vector fixes either an admission refusal — its stable code
 * and schema JSON Pointer — or an accepted schema's instance verdicts with
 * the first diagnostic's keyword and instance pointer. Producing the same
 * answers is what makes this implementation verdict-compatible with every
 * other conforming runtime. The corpus stops at each schema's first
 * refusal, so the remaining rejection arms, applicator branches and
 * determinism guarantees are exercised here deliberately, one crafted
 * schema per edge.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Schema\SchemaAdmissionException;
use Kumwe\Producer\Schema\SchemaPropertyProfile;
use Kumwe\Producer\Tests\TestCase;

final class SchemaPropertyProfileTest extends TestCase
{
    public function testReplaysTheCompleteSchemaProfileCorpus(): void
    {
        $directory = dirname(__DIR__, 2) . '/resources/studio-contract/conformance/schema-profile';
        $files = glob($directory . '/*.json') ?: [];
        sort($files);
        $this->assertSame(62, count($files), 'The complete schema-profile corpus must be vendored.');

        foreach ($files as $file) {
            $vector = CanonicalJson::decode((string) file_get_contents($file));
            $label = $vector->id;
            $expect = $vector->expect;

            if ($expect->outcome === 'rejected') {
                $error = $this->assertThrows(
                    static fn () => SchemaPropertyProfile::admit($vector->schema),
                    SchemaAdmissionException::class,
                    "{$label} must be refused."
                );
                $this->assertSame(
                    $expect->code,
                    $error->rejection(),
                    "{$label} must carry the stable rejection code."
                );
                $this->assertSame(
                    $expect->schemaPath,
                    $error->schemaPath(),
                    "{$label} must point at the published schema location."
                );
                continue;
            }

            $validator = SchemaPropertyProfile::admit($vector->schema);
            foreach ($expect->instances as $index => $case) {
                $valid = $validator->validate($case->value ?? null);
                $this->assertSame(
                    $case->valid,
                    $valid,
                    "{$label} instance {$index} must reach the published verdict."
                );
                if (!isset($case->diagnostic)) {
                    continue;
                }
                $first = $validator->diagnostics()[0] ?? null;
                $this->assertTrue(
                    $first !== null,
                    "{$label} instance {$index} must carry its first diagnostic."
                );
                $this->assertSame(
                    $case->diagnostic->instancePath,
                    $first->instancePath,
                    "{$label} instance {$index} first diagnostic instance pointer must match."
                );
                $this->assertSame(
                    $case->diagnostic->keyword,
                    $first->keyword,
                    "{$label} instance {$index} first diagnostic keyword must match."
                );
            }
        }
    }

    public function testLimitsPinToTheVendoredMetaSchema(): void
    {
        $metaSchema = CanonicalJson::decode((string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/studio-contract/schemas/schema-profile.schema.json'
        ));
        $published = $metaSchema->{'$defs'}->limits->const;

        $expected = [];
        foreach (get_object_vars($published) as $limit => $value) {
            $expected[$limit] = $value;
        }
        ksort($expected);
        $actual = SchemaPropertyProfile::LIMITS;
        ksort($actual);
        $this->assertSame($expected, $actual, 'The limits must pin to the vendored meta-schema exactly.');
    }

    public function testRejectionArmsTheCorpusLeavesUntaken(): void
    {
        $root = '"type":"object","additionalProperties":false';
        $arms = [
            'schema position holds a scalar' => [
                '{' . $root . ',"items":5}', 'invalid-keyword-value', '/items',
            ],
            'schema map is an array' => [
                '{' . $root . ',"properties":[]}', 'invalid-keyword-value', '/properties',
            ],
            'schema map member is a scalar' => [
                '{' . $root . ',"$defs":{"x":5}}', 'invalid-keyword-value', '/$defs/x',
            ],
            'composition operand is an object' => [
                '{' . $root . ',"allOf":{}}', 'invalid-keyword-value', '/allOf',
            ],
            'enum operand is a scalar' => [
                '{' . $root . ',"enum":5}', 'invalid-keyword-value', '/enum',
            ],
            'enum repeats a canonical value across numeric types' => [
                '{' . $root . ',"enum":[1,1.0]}', 'invalid-keyword-value', '/enum/1',
            ],
            'examples operand is a scalar' => [
                '{' . $root . ',"examples":5}', 'invalid-keyword-value', '/examples',
            ],
            'dependentRequired operand is an array' => [
                '{' . $root . ',"dependentRequired":[]}', 'invalid-keyword-value', '/dependentRequired',
            ],
            'required operand is an object' => [
                '{' . $root . ',"required":{}}', 'invalid-keyword-value', '/required',
            ],
            'required member is not a string' => [
                '{' . $root . ',"required":[5]}', 'invalid-keyword-value', '/required/0',
            ],
            'type names an unknown type' => [
                '{"additionalProperties":false,"type":"text"}', 'invalid-keyword-value', '/type',
            ],
            'type array is empty' => [
                '{"additionalProperties":false,"type":[]}', 'invalid-keyword-value', '/type',
            ],
            'type array repeats a name' => [
                '{' . $root . ',"properties":{"a":{"type":["string","string"]}}}',
                'invalid-keyword-value', '/properties/a/type/1',
            ],
            'reference resolves to a non-schema position' => [
                '{' . $root . ',"title":"x","$ref":"#/title"}', 'invalid-reference', '/$ref',
            ],
            'dialect is not Draft 2020-12' => [
                '{' . $root . ',"$schema":"https://json-schema.org/draft/2019-09/schema"}',
                'invalid-keyword-value', '/$schema',
            ],
            'title is not a string' => [
                '{' . $root . ',"title":5}', 'invalid-keyword-value', '/title',
            ],
            'minLength is negative' => [
                '{' . $root . ',"minLength":-1}', 'invalid-keyword-value', '/minLength',
            ],
            'minimum is not a number' => [
                '{' . $root . ',"minimum":"5"}', 'invalid-keyword-value', '/minimum',
            ],
            'multipleOf is zero' => [
                '{' . $root . ',"multipleOf":0}', 'invalid-keyword-value', '/multipleOf',
            ],
            'readOnly is not a boolean' => [
                '{' . $root . ',"readOnly":"yes"}', 'invalid-keyword-value', '/readOnly',
            ],
            'root type declares a scalar' => [
                '{"additionalProperties":false,"type":"string"}', 'invalid-root', '/type',
            ],
        ];

        foreach ($arms as $label => [$schema, $code, $schemaPath]) {
            $decoded = CanonicalJson::decode($schema);
            $error = $this->assertThrows(
                static fn () => SchemaPropertyProfile::admit($decoded),
                SchemaAdmissionException::class,
                "The '{$label}' schema must be refused."
            );
            $this->assertSame($code, $error->rejection(), "The '{$label}' rejection code must match.");
            $this->assertSame($schemaPath, $error->schemaPath(), "The '{$label}' schema pointer must match.");
        }
    }

    public function testOversizedDependencyMapIsRefused(): void
    {
        $dependencies = new \stdClass();
        for ($index = 0; $index <= SchemaPropertyProfile::LIMITS['maxSchemaMapProperties']; $index++) {
            $dependencies->{'p' . $index} = [];
        }
        $schema = self::closedRoot();
        $schema->dependentRequired = $dependencies;

        $error = $this->assertThrows(
            static fn () => SchemaPropertyProfile::admit($schema),
            SchemaAdmissionException::class,
            'An oversized dependency map must be refused.'
        );
        $this->assertSame('limit-exceeded', $error->rejection(), 'The dependency-map ceiling names its code.');
        $this->assertSame('/dependentRequired', $error->schemaPath(), 'The dependency-map ceiling names its map.');
    }

    public function testHandBuiltNonJsonOperandsAreRefused(): void
    {
        $withMap = self::closedRoot();
        $withMap->const = ['a' => 1];
        $error = $this->assertThrows(
            static fn () => SchemaPropertyProfile::admit($withMap),
            SchemaAdmissionException::class,
            'An associative-array operand must be refused.'
        );
        $this->assertSame('invalid-keyword-value', $error->rejection(), 'A non-list array is not JSON.');
        $this->assertSame('/const', $error->schemaPath(), 'The associative array is refused where it sits.');

        $withObject = self::closedRoot();
        $withObject->default = new \DateTimeImmutable();
        $error = $this->assertThrows(
            static fn () => SchemaPropertyProfile::admit($withObject),
            SchemaAdmissionException::class,
            'A non-JSON object operand must be refused.'
        );
        $this->assertSame('invalid-keyword-value', $error->rejection(), 'A foreign object is not JSON.');
        $this->assertSame('/default', $error->schemaPath(), 'The foreign object is refused where it sits.');

        $shared = new \stdClass();
        $shared->type = 'string';
        $reusing = self::closedRoot();
        $reusing->properties = new \stdClass();
        $reusing->properties->a = $shared;
        $reusing->properties->b = $shared;
        $error = $this->assertThrows(
            static fn () => SchemaPropertyProfile::admit($reusing),
            SchemaAdmissionException::class,
            'A reused schema object must be refused.'
        );
        $this->assertSame('invalid-root', $error->rejection(), 'Aliasing breaks the document shape.');
        $this->assertSame('/properties/b', $error->schemaPath(), 'The second appearance is the refused one.');

        $poisoned = self::closedRoot();
        $poisoned->properties = new \stdClass();
        $poisoned->properties->x = new \stdClass();
        $poisoned->properties->x->enum = [INF];
        $error = $this->assertThrows(
            static fn () => SchemaPropertyProfile::admit($poisoned),
            SchemaAdmissionException::class,
            'A schema carrying a non-finite number must not admit.'
        );
        $this->assertSame('invalid-keyword-value', $error->rejection(), 'A non-finite number is not JSON.');
    }

    public function testValidatorEdgeKeywords(): void
    {
        $validator = SchemaPropertyProfile::admit(CanonicalJson::decode(<<<'JSON'
            {
                "type": "object",
                "additionalProperties": false,
                "properties": {
                    "list": {
                        "type": "array",
                        "prefixItems": [{"type": "integer"}],
                        "items": false,
                        "uniqueItems": true,
                        "minItems": 1,
                        "maxItems": 3
                    },
                    "uniq": {"uniqueItems": true},
                    "word": {"type": "string", "minLength": 2, "maxLength": 4},
                    "count": {"type": ["integer", "null"], "minimum": 1, "exclusiveMaximum": 10},
                    "choice": {"oneOf": [{"minimum": 0}, {"maximum": 100}]},
                    "other": {"not": {"const": 3}, "if": {"minimum": 5}, "else": {"const": 1}}
                },
                "propertyNames": {"maxLength": 6},
                "dependentRequired": {"word": ["count"]},
                "minProperties": 1,
                "maxProperties": 5
            }
            JSON));

        $this->assertTrue(
            $validator->validate(CanonicalJson::decode('{"list":[7],"word":"hi","count":9}')),
            'The conforming instance must pass every edge keyword.'
        );

        $failing = [
            'prefix overflow under items false' => ['{"list":[7,8]}', 'items', '/list'],
            'duplicate items' => ['{"uniq":[1,1]}', 'uniqueItems', '/uniq'],
            'string too long' => ['{"word":"toolong","count":1}', 'maxLength', '/word'],
            'exclusive maximum met' => ['{"count":10}', 'exclusiveMaximum', '/count'],
            'oneOf matches both alternatives' => ['{"choice":50}', 'oneOf', '/choice'],
            'negated constant matched' => ['{"other":3}', 'not', '/other'],
            'else branch refused' => ['{"other":2}', 'const', '/other'],
            'dependent member missing' => ['{"word":"hi"}', 'dependentRequired', ''],
            'no properties at all' => ['{}', 'minProperties', ''],
        ];
        foreach ($failing as $label => [$instance, $keyword, $path]) {
            $this->assertSame(
                false,
                $validator->validate(CanonicalJson::decode($instance)),
                "The '{$label}' instance must fail."
            );
            $first = $validator->diagnostics()[0] ?? null;
            $this->assertTrue($first !== null, "The '{$label}' failure must carry a diagnostic.");
            $this->assertSame($keyword, $first->keyword, "The '{$label}' first keyword must match.");
            $this->assertSame($path, $first->instancePath, "The '{$label}' first instance pointer must match.");
        }

        $named = SchemaPropertyProfile::admit(CanonicalJson::decode(
            '{"type":"object","additionalProperties":false,"properties":{"long":{"type":"integer"}},'
            . '"propertyNames":{"maxLength":2}}'
        ));
        $this->assertSame(false, $named->validate(CanonicalJson::decode('{"long":1}')), 'The long name must fail.');
        $this->assertSame(
            'propertyNames',
            $named->diagnostics()[0]->keyword ?? null,
            'A rejected member name reports propertyNames.'
        );

        $closed = SchemaPropertyProfile::admit(CanonicalJson::decode(
            '{"type":"object","additionalProperties":false,"properties":{"x":{"prefixItems":[false]}}}'
        ));
        $this->assertSame(false, $closed->validate(CanonicalJson::decode('{"x":[1]}')), 'false refuses its item.');
        $this->assertSame('false', $closed->diagnostics()[0]->keyword ?? null, 'The false schema names itself.');
        $this->assertSame('/x/0', $closed->diagnostics()[0]->instancePath ?? null, 'The item position is named.');
        $this->assertTrue($closed->validate(CanonicalJson::decode('{}')), 'An absent member takes no false branch.');
    }

    public function testDecimalMultipleAndMemoDeterminism(): void
    {
        $validator = SchemaPropertyProfile::admit(CanonicalJson::decode(
            '{"type":"object","additionalProperties":false,'
            . '"properties":{"n":{"multipleOf":0.01}},"$defs":{"f":{"required":["missing"]}},'
            . '"anyOf":[{"$ref":"#/$defs/f"},true],"if":true,"then":{"$ref":"#/$defs/f"}}'
        ));
        $zero = CanonicalJson::decode('{"n":0}');
        $this->assertSame(false, $validator->validate($zero), 'The then-branch still demands the member.');
        $this->assertSame(
            'required',
            $validator->diagnostics()[0]->keyword ?? null,
            'Zero is a multiple of every divisor, so only required fails.'
        );
        $first = self::diagnosticTuples($validator->diagnostics());
        $this->assertSame(false, $validator->validate($zero), 'A repeat run reaches the same verdict.');
        $this->assertSame(
            $first,
            self::diagnosticTuples($validator->diagnostics()),
            'Diagnostics are deterministic across runs.'
        );
    }

    public function testApplicatorBranchesRefuseTheirMismatches(): void
    {
        $root = self::closedRoot();
        $root->properties = CanonicalJson::decode((string) json_encode([
            'union' => ['type' => ['string', 'null']],
            'both' => ['allOf' => [['type' => 'string'], ['minLength' => 3]]],
            'either' => ['anyOf' => [['type' => 'string'], ['type' => 'integer']]],
            'flag' => ['type' => 'boolean'],
            'sized' => ['type' => 'string', 'minLength' => 2],
            'low' => ['type' => 'number', 'maximum' => 10, 'exclusiveMinimum' => 0],
            'pair' => ['prefixItems' => [['type' => 'string']], 'items' => false],
            'tail' => ['prefixItems' => [['type' => 'string']], 'items' => ['type' => 'integer']],
            'filled' => ['type' => 'array', 'minItems' => 2],
            'map' => ['type' => 'object', 'additionalProperties' => ['type' => 'integer']],
            'small' => ['type' => 'object', 'maxProperties' => 1],
        ]));
        $validator = SchemaPropertyProfile::admit($root);

        $failures = [
            'type list' => ['{"union":5}', '/union', 'must be string,null'],
            'allOf' => ['{"both":5}', '/both', 'must match all schemas in allOf'],
            'anyOf' => ['{"either":true}', '/either', 'must match a schema in anyOf'],
            'minLength' => ['{"sized":"a"}', '/sized', 'must NOT have fewer than 2 characters'],
            'maximum' => ['{"low":11}', '/low', 'must be <= 10'],
            'exclusiveMinimum' => ['{"low":0}', '/low', 'must be > 0'],
            'closed tuple' => ['{"pair":["a","b"]}', '/pair', 'must NOT have more than 1 items'],
            'typed tail' => ['{"tail":["a","b"]}', '/tail/1', 'must be integer'],
            'minItems' => ['{"filled":[]}', '/filled', 'must NOT have fewer than 2 items'],
            'typed additional' => ['{"map":{"x":"no"}}', '/map/x', 'must be integer'],
            'maxProperties' => ['{"small":{"a":1,"b":2}}', '/small', 'must NOT have more than 1 properties'],
        ];
        foreach ($failures as $label => [$instance, $path, $message]) {
            $this->assertSame(
                false,
                $validator->validate(CanonicalJson::decode($instance)),
                "The '{$label}' instance must fail."
            );
            $found = false;
            foreach ($validator->diagnostics() ?? [] as $diagnostic) {
                if ($diagnostic->instancePath === $path && $diagnostic->message === $message) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "The '{$label}' failure must surface at {$path}.");
        }

        $this->assertTrue(
            $validator->validate(CanonicalJson::decode('{"union":"text","flag":true,"low":5,"pair":["a"],"tail":["a",1,2]}')),
            'The matching instance must pass every branch.'
        );
    }

    public function testStructuredEqualityAndDiagnosticDeduplication(): void
    {
        $root = self::closedRoot();
        $root->properties = CanonicalJson::decode((string) json_encode([
            'exact' => ['const' => [1, ['a' => true]]],
            'pick' => ['enum' => [null, true, ['b' => 2], [1, 2]]],
            'twice' => ['allOf' => [['type' => 'string'], ['type' => 'string']]],
        ]));
        $validator = SchemaPropertyProfile::admit($root);

        $this->assertTrue(
            $validator->validate(CanonicalJson::decode('{"exact":[1,{"a":true}],"pick":[1,2]}')),
            'Deep-equal structures must satisfy const and enum.'
        );
        $this->assertSame(
            false,
            $validator->validate(CanonicalJson::decode('{"exact":[1,{"a":false}],"pick":{"b":3}}')),
            'Structurally different values must fail const and enum.'
        );

        $this->assertSame(
            false,
            $validator->validate(CanonicalJson::decode('{"twice":5}')),
            'Twin subschemas must both refuse the mismatch.'
        );
        $typeFailures = 0;
        foreach ($validator->diagnostics() ?? [] as $diagnostic) {
            if ($diagnostic->instancePath === '/twice' && $diagnostic->message === 'must be string') {
                $typeFailures++;
            }
        }
        $this->assertSame(1, $typeFailures, 'Twin diagnostics from twin subschemas deduplicate.');
    }

    public function testReferenceResolutionWalksEveryOperandFamily(): void
    {
        $shared = self::closedRoot();
        $shared->{'$defs'} = CanonicalJson::decode('{"name":{"type":"string"}}');
        $shared->properties = CanonicalJson::decode(
            '{"first":{"$ref":"#/$defs/name"},"second":{"$ref":"#/$defs/name"}}'
        );
        $validator = SchemaPropertyProfile::admit($shared);
        $this->assertTrue(
            $validator->validate(CanonicalJson::decode('{"first":"a","second":"b"}')),
            'A twice-referenced definition resolves once and applies at both sites.'
        );

        $arrayTarget = self::closedRoot();
        $arrayTarget->allOf = CanonicalJson::decode('[{"type":"object"}]');
        $arrayTarget->properties = CanonicalJson::decode('{"inner":{"$ref":"#/allOf/0"}}');
        $validator = SchemaPropertyProfile::admit($arrayTarget);
        $this->assertTrue(
            $validator->validate(CanonicalJson::decode('{"inner":{}}')),
            'A schema-array position is referenceable.'
        );

        $whole = self::closedRoot();
        $whole->properties = CanonicalJson::decode('{"loop":{"$ref":"#"}}');
        $error = $this->assertThrows(
            static fn () => SchemaPropertyProfile::admit($whole),
            SchemaAdmissionException::class,
            'A whole-document self reference must not admit.'
        );
        $this->assertSame('recursive-schema', $error->rejection(), 'The self reference is recursion.');
    }

    /**
     * A closed object root ready to be extended by one keyword under test.
     */
    private static function closedRoot(): \stdClass
    {
        $root = new \stdClass();
        $root->type = 'object';
        $root->additionalProperties = false;

        return $root;
    }

    /**
     * Project diagnostics onto comparable tuples.
     *
     * @param  list<\Kumwe\Producer\Schema\SchemaInstanceDiagnostic>|null $diagnostics
     * @return list<array{0: string, 1: string, 2: string}>|null
     */
    private static function diagnosticTuples(?array $diagnostics): ?array
    {
        if ($diagnostics === null) {
            return null;
        }
        $tuples = [];
        foreach ($diagnostics as $diagnostic) {
            $tuples[] = [$diagnostic->instancePath, $diagnostic->keyword, $diagnostic->message];
        }

        return $tuples;
    }
}
