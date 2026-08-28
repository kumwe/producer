<?php

declare(strict_types=1);

namespace Kumwe\Producer\Schema;

use Kumwe\Producer\Canonical\CanonicalJson;

/**
 * Admission for `studio.profile/schema-property`, Studio's closed profile
 * for contributed block property schemas.
 *
 * The profile is deliberately object-rooted, closed, local-reference-only,
 * non-recursive and format-free, with every complexity ceiling published in
 * the pinned meta-schema's `$defs/limits`. Admission enforces the closed
 * keyword set and operand grammar, measures the canonical UTF-8 byte budget
 * before sorting or recursing into untrusted maps, proves the reference
 * graph acyclic, and holds the root to `{ "type": "object",
 * "additionalProperties": false }`. Refusals carry one of the seven closed
 * codes and a JSON Pointer, and when more than one member is invalid the
 * first diagnostic follows the published order: object members by UTF-16
 * code unit, array members by numeric index, a container before its
 * descendants, and missing root invariants at their virtual member
 * positions. Studio's schema-profile conformance vectors prove this
 * implementation reaches the same verdicts as every other conforming
 * runtime.
 *
 * Values follow PHP's decoded-JSON shape: objects are stdClass and arrays
 * are lists, exactly as {@see CanonicalJson::decode()} produces them.
 *
 * @since   0.1.0
 */
final class SchemaPropertyProfile
{
    /**
     * Published complexity ceilings, pinned to `$defs/limits` in the
     * vendored meta-schema.
     *
     * @var array<string, int>
     *
     * @since   0.1.0
     */
    public const LIMITS = [
        'maxAlternatives' => 64,
        'maxDescriptionLength' => 10000,
        'maxEnumMembers' => 1024,
        'maxExamples' => 100,
        'maxJsonDepth' => 64,
        'maxJsonItems' => 10000,
        'maxJsonProperties' => 1000,
        'maxObjectKeyLength' => 200,
        'maxPropertyNames' => 512,
        'maxReferenceLength' => 500,
        'maxReferences' => 128,
        'maxSchemaBytes' => 262144,
        'maxSchemaDepth' => 32,
        'maxSchemaMapProperties' => 512,
        'maxSchemaNodes' => 1024,
        'maxTitleLength' => 1000,
    ];

    /**
     * The one dialect a schema may declare.
     *
     * @since   0.1.0
     */
    private const DRAFT_2020_12 = 'https://json-schema.org/draft/2020-12/schema';

    /**
     * The closed keyword set contributed schemas may use.
     *
     * @var array<string, true>
     *
     * @since   0.1.0
     */
    private const ALLOWED_KEYWORDS = [
        '$defs' => true, '$ref' => true, '$schema' => true, 'additionalProperties' => true,
        'allOf' => true, 'anyOf' => true, 'const' => true, 'default' => true,
        'dependentRequired' => true, 'description' => true, 'else' => true, 'enum' => true,
        'examples' => true, 'exclusiveMaximum' => true, 'exclusiveMinimum' => true, 'if' => true,
        'items' => true, 'maxItems' => true, 'maxLength' => true, 'maxProperties' => true,
        'maximum' => true, 'minItems' => true, 'minLength' => true, 'minProperties' => true,
        'minimum' => true, 'multipleOf' => true, 'not' => true, 'oneOf' => true,
        'prefixItems' => true, 'properties' => true, 'propertyNames' => true, 'readOnly' => true,
        'required' => true, 'then' => true, 'title' => true, 'type' => true,
        'uniqueItems' => true, 'writeOnly' => true,
    ];

    /**
     * The closed JSON Schema type-name set.
     *
     * @var array<string, true>
     *
     * @since   0.1.0
     */
    private const TYPE_NAMES = [
        'array' => true, 'boolean' => true, 'integer' => true, 'null' => true,
        'number' => true, 'object' => true, 'string' => true,
    ];

    /**
     * Local `$ref` grammar: `#`, or a `#/` pointer whose raw tokens use the
     * portable ASCII subset.
     *
     * @since   0.1.0
     */
    private const REFERENCE_GRAMMAR = "/^#(?:\\/(?:[A-Za-z0-9._!\$&'()*+,;=:@-]|~[01])*)*$/";

    /**
     * References seen so far in this admission.
     *
     * @since   0.1.0
     */
    private int $references = 0;

    /**
     * Schema nodes counted so far in this admission.
     *
     * @since   0.1.0
     */
    private int $schemaNodes = 0;

    /**
     * Objects already visited, to refuse aliasing and cycles.
     *
     * @var \SplObjectStorage<object, mixed>
     *
     * @since   0.1.0
     */
    private \SplObjectStorage $seen;

    /**
     * Start one admission run with empty counters; only {@see admit()}
     * constructs an instance.
     *
     * @since   0.1.0
     */
    private function __construct()
    {
        $this->seen = new \SplObjectStorage();
    }

    /**
     * Admit one contributed property schema and hand back its interpreting
     * validator, or throw the first refusal under the published diagnostic
     * order.
     *
     * @param mixed $schema Decoded schema document: objects as stdClass,
     *                      arrays as lists, exactly as `json_decode` without
     *                      associative mode produces.
     *
     * @throws SchemaAdmissionException When the schema falls outside the profile.
     *
     * @since   0.1.0
     */
    public static function admit(mixed $schema): SchemaPropertyValidator
    {
        if (!$schema instanceof \stdClass) {
            throw new SchemaAdmissionException(
                'invalid-root',
                '',
                'Studio property schema root must be a JSON Schema object.'
            );
        }

        if (!self::withinCanonicalByteBudget($schema)) {
            throw new SchemaAdmissionException(
                'limit-exceeded',
                '',
                sprintf('Studio property schema exceeds %d canonical UTF-8 bytes.', self::LIMITS['maxSchemaBytes'])
            );
        }

        $admission = new self();
        $failures = [];
        foreach (
            [
                static fn () => $admission->visitSchema($schema, '', 1),
                static fn () => $admission->assertNonRecursive($schema),
                static fn () => self::assertClosedObjectRoot($schema),
            ] as $pass
        ) {
            try {
                $pass();
            } catch (SchemaAdmissionException $failure) {
                $failures[] = $failure;
            }
        }
        $first = self::firstFailure($schema, $failures);
        if ($first !== null) {
            throw $first;
        }

        return new SchemaPropertyValidator($schema, self::resolveReferences($schema));
    }

    /**
     * Walk one schema node, enforcing the closed keyword set and every
     * operand grammar, refusing at the node's first failure in code-unit
     * member order.
     *
     * @param mixed  $value Value at this schema position; anything but an
     *                      object is refused.
     * @param string $path  Schema JSON Pointer to this position; the empty
     *                      string is the root.
     * @param int    $depth Schema nesting depth of this position, root = 1.
     *
     * @throws SchemaAdmissionException At the node's first failure.
     *
     * @since   0.1.0
     */
    private function visitSchema(mixed $value, string $path, int $depth): void
    {
        if (!$value instanceof \stdClass) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must be a JSON Schema object.'
            );
        }
        $this->trackObject($value, $path);
        $this->trackSchemaNode($path, $depth);

        foreach ($this->boundedSchemaEntries($value) as $keyword) {
            $operand = $value->{$keyword};
            $keywordPath = self::appendPointer($path, $keyword);
            self::assertSafeObjectKey($keyword, $path);
            if (!isset(self::ALLOWED_KEYWORDS[$keyword])) {
                throw new SchemaAdmissionException(
                    'unsupported-keyword',
                    $keywordPath,
                    sprintf(
                        '%s uses keyword "%s", which is not allowed by the Studio Schema Profile.',
                        self::displayPath($keywordPath),
                        $keyword
                    )
                );
            }

            match ($keyword) {
                '$defs', 'properties' => $this->visitSchemaMap($operand, $keywordPath, $depth + 1),
                'additionalProperties', 'else', 'if', 'items', 'not', 'propertyNames', 'then'
                    => $this->visitSubschema($operand, $keywordPath, $depth + 1),
                'allOf', 'anyOf', 'oneOf', 'prefixItems'
                    => $this->visitSchemaArray($operand, $keywordPath, $depth + 1),
                '$ref' => $this->visitReference($operand, $keywordPath),
                '$schema' => self::assertDialect($operand, $keywordPath),
                'enum' => $this->visitEnum($operand, $keywordPath, 1),
                'examples' => $this->visitExamples($operand, $keywordPath, 1),
                'dependentRequired' => $this->visitDependentRequired($operand, $keywordPath),
                'required' => $this->visitNameArray($operand, $keywordPath, self::LIMITS['maxPropertyNames']),
                'type' => $this->visitType($operand, $keywordPath),
                'description' => self::visitBoundedString($operand, $keywordPath, self::LIMITS['maxDescriptionLength']),
                'title' => self::visitBoundedString($operand, $keywordPath, self::LIMITS['maxTitleLength']),
                'maxItems', 'maxLength', 'maxProperties', 'minItems', 'minLength', 'minProperties'
                    => self::visitNonNegativeInteger($operand, $keywordPath),
                'exclusiveMaximum', 'exclusiveMinimum', 'maximum', 'minimum'
                    => self::visitFiniteNumber($operand, $keywordPath),
                'multipleOf' => self::visitPositiveNumber($operand, $keywordPath),
                'readOnly', 'uniqueItems', 'writeOnly' => self::visitBoolean($operand, $keywordPath),
                // Only `const` and `default` remain once every other allowed keyword is matched.
                default => $this->visitJsonValue($operand, $keywordPath, 1),
            };
        }
    }

    /**
     * Walk a `$defs` or `properties` map in code-unit order.
     *
     * @param mixed  $value Keyword operand; must be an object of schemas.
     * @param string $path  Schema JSON Pointer to the operand.
     * @param int    $depth Schema nesting depth of the entries.
     *
     * @throws SchemaAdmissionException At the map's first failure.
     *
     * @since   0.1.0
     */
    private function visitSchemaMap(mixed $value, string $path, int $depth): void
    {
        if (!$value instanceof \stdClass) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must be an object of schemas.'
            );
        }
        $this->trackObject($value, $path);
        $names = self::memberNames($value);
        if (count($names) > self::LIMITS['maxSchemaMapProperties']) {
            throw new SchemaAdmissionException(
                'limit-exceeded',
                $path,
                sprintf(
                    '%s exceeds %d schema entries.',
                    self::displayPath($path),
                    self::LIMITS['maxSchemaMapProperties']
                )
            );
        }
        usort($names, CodeUnitOrder::compare(...));
        foreach ($names as $name) {
            self::assertSafeObjectKey($name, $path);
            $this->visitSchema($value->{$name}, self::appendPointer($path, $name), $depth);
        }
    }

    /**
     * Walk a composition array of subschemas: dense, non-empty, bounded.
     *
     * @param mixed  $value Keyword operand; must be a dense list of at most
     *                      `maxAlternatives` schemas.
     * @param string $path  Schema JSON Pointer to the operand.
     * @param int    $depth Schema nesting depth of the members.
     *
     * @throws SchemaAdmissionException At the array's first failure.
     *
     * @since   0.1.0
     */
    private function visitSchemaArray(mixed $value, string $path, int $depth): void
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must be a dense JSON array of schemas.'
            );
        }
        if ($value === []) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must contain at least one schema.'
            );
        }
        if (count($value) > self::LIMITS['maxAlternatives']) {
            throw new SchemaAdmissionException(
                'limit-exceeded',
                $path,
                sprintf(
                    '%s must contain at most %d schemas.',
                    self::displayPath($path),
                    self::LIMITS['maxAlternatives']
                )
            );
        }
        foreach ($value as $index => $member) {
            $this->visitSubschema($member, self::appendPointer($path, (string) $index), $depth);
        }
    }

    /**
     * Walk one subschema position, where a bare boolean is a complete schema.
     *
     * @param mixed  $value Value at the position: a boolean or a schema object.
     * @param string $path  Schema JSON Pointer to the position.
     * @param int    $depth Schema nesting depth of the position.
     *
     * @throws SchemaAdmissionException At the subschema's first failure.
     *
     * @since   0.1.0
     */
    private function visitSubschema(mixed $value, string $path, int $depth): void
    {
        if (is_bool($value)) {
            $this->trackSchemaNode($path, $depth);

            return;
        }
        $this->visitSchema($value, $path, $depth);
    }

    /**
     * Count one schema node against the depth and node ceilings.
     *
     * @param string $path  Schema JSON Pointer to the node, for the refusal.
     * @param int    $depth Schema nesting depth of the node, root = 1.
     *
     * @throws SchemaAdmissionException `limit-exceeded` past either ceiling.
     *
     * @since   0.1.0
     */
    private function trackSchemaNode(string $path, int $depth): void
    {
        if ($depth > self::LIMITS['maxSchemaDepth']) {
            throw new SchemaAdmissionException(
                'limit-exceeded',
                $path,
                self::displayPath($path) . ' exceeds the Studio Schema Profile depth limit.'
            );
        }
        $this->schemaNodes++;
        if ($this->schemaNodes > self::LIMITS['maxSchemaNodes']) {
            throw new SchemaAdmissionException(
                'limit-exceeded',
                $path,
                sprintf('Studio property schema exceeds %d schema nodes.', self::LIMITS['maxSchemaNodes'])
            );
        }
    }

    /**
     * Check one `$ref` operand against the portable local grammar and the
     * reference ceiling.
     *
     * @param mixed  $value Keyword operand; must be a portable bounded local
     *                      JSON Pointer reference.
     * @param string $path  Schema JSON Pointer to the operand.
     *
     * @throws SchemaAdmissionException `invalid-reference` outside the
     *                                  grammar; `limit-exceeded` past the
     *                                  reference ceiling.
     *
     * @since   0.1.0
     */
    private function visitReference(mixed $value, string $path): void
    {
        if (!self::isPortableLocalReference($value)) {
            throw new SchemaAdmissionException(
                'invalid-reference',
                $path,
                self::displayPath($path) . ' must be a bounded local JSON Pointer reference.'
            );
        }
        $this->references++;
        if ($this->references > self::LIMITS['maxReferences']) {
            throw new SchemaAdmissionException(
                'limit-exceeded',
                $path,
                sprintf('Studio property schema exceeds %d references.', self::LIMITS['maxReferences'])
            );
        }
    }

    /**
     * Check an `enum` operand: dense, non-empty, bounded, and canonically
     * unique — members are distinct exactly when their canonical
     * serializations differ.
     *
     * @param mixed  $value Keyword operand; must be a dense list of bounded
     *                      JSON values.
     * @param string $path  Schema JSON Pointer to the operand.
     * @param int    $depth JSON nesting depth of the members, top level = 1.
     *
     * @throws SchemaAdmissionException At the operand's first failure.
     *
     * @since   0.1.0
     */
    private function visitEnum(mixed $value, string $path, int $depth): void
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must be a dense JSON array.'
            );
        }
        if ($value === []) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must contain at least one value.'
            );
        }
        if (count($value) > self::LIMITS['maxEnumMembers']) {
            throw new SchemaAdmissionException(
                'limit-exceeded',
                $path,
                sprintf('%s exceeds %d members.', self::displayPath($path), self::LIMITS['maxEnumMembers'])
            );
        }
        $members = [];
        foreach ($value as $index => $member) {
            $this->visitJsonValue($member, self::appendPointer($path, (string) $index), $depth);
            $canonical = CanonicalJson::stringify($member, self::LIMITS['maxJsonDepth'] + 1);
            if (isset($members[$canonical])) {
                throw new SchemaAdmissionException(
                    'invalid-keyword-value',
                    self::appendPointer($path, (string) $index),
                    self::displayPath($path) . ' must contain unique JSON values.'
                );
            }
            $members[$canonical] = true;
        }
    }

    /**
     * Check an `examples` operand: dense and bounded, each member a bounded
     * JSON value.
     *
     * @param mixed  $value Keyword operand; must be a dense list of at most
     *                      `maxExamples` JSON values.
     * @param string $path  Schema JSON Pointer to the operand.
     * @param int    $depth JSON nesting depth of the members, top level = 1.
     *
     * @throws SchemaAdmissionException At the operand's first failure.
     *
     * @since   0.1.0
     */
    private function visitExamples(mixed $value, string $path, int $depth): void
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must be a dense JSON array.'
            );
        }
        if (count($value) > self::LIMITS['maxExamples']) {
            throw new SchemaAdmissionException(
                'limit-exceeded',
                $path,
                sprintf('%s exceeds %d examples.', self::displayPath($path), self::LIMITS['maxExamples'])
            );
        }
        foreach ($value as $index => $example) {
            $this->visitJsonValue($example, self::appendPointer($path, (string) $index), $depth);
        }
    }

    /**
     * Check a `dependentRequired` operand: a bounded map of safe names to
     * name arrays, walked in code-unit order.
     *
     * @param mixed  $value Keyword operand; must be an object of
     *                      property-name arrays.
     * @param string $path  Schema JSON Pointer to the operand.
     *
     * @throws SchemaAdmissionException At the operand's first failure.
     *
     * @since   0.1.0
     */
    private function visitDependentRequired(mixed $value, string $path): void
    {
        if (!$value instanceof \stdClass) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must be an object of property-name arrays.'
            );
        }
        $this->trackObject($value, $path);
        $names = self::memberNames($value);
        if (count($names) > self::LIMITS['maxSchemaMapProperties']) {
            throw new SchemaAdmissionException(
                'limit-exceeded',
                $path,
                sprintf(
                    '%s exceeds %d dependency entries.',
                    self::displayPath($path),
                    self::LIMITS['maxSchemaMapProperties']
                )
            );
        }
        usort($names, CodeUnitOrder::compare(...));
        foreach ($names as $name) {
            self::assertSafeObjectKey($name, $path);
            $this->visitNameArray(
                $value->{$name},
                self::appendPointer($path, $name),
                self::LIMITS['maxPropertyNames']
            );
        }
    }

    /**
     * Check a property-name array: dense, bounded, safe, unique strings.
     *
     * @param mixed  $value   Operand; must be a dense list of strings.
     * @param string $path    Schema JSON Pointer to the array.
     * @param int    $maximum Most names the array may hold.
     *
     * @throws SchemaAdmissionException At the array's first failure.
     *
     * @since   0.1.0
     */
    private function visitNameArray(mixed $value, string $path, int $maximum): void
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must be a dense array of property names.'
            );
        }
        if (count($value) > $maximum) {
            throw new SchemaAdmissionException(
                'limit-exceeded',
                $path,
                sprintf('%s exceeds %d property names.', self::displayPath($path), $maximum)
            );
        }
        $names = [];
        foreach ($value as $index => $name) {
            if (!is_string($name)) {
                throw new SchemaAdmissionException(
                    'invalid-keyword-value',
                    self::appendPointer($path, (string) $index),
                    self::displayPath($path) . ' must contain only property-name strings.'
                );
            }
            self::assertSafeObjectKey($name, $path, self::appendPointer($path, (string) $index));
            if (isset($names[$name])) {
                throw new SchemaAdmissionException(
                    'invalid-keyword-value',
                    self::appendPointer($path, (string) $index),
                    self::displayPath($path) . ' must list unique property names.'
                );
            }
            $names[$name] = true;
        }
    }

    /**
     * Check a `type` operand: one known name, or a bounded unique array of
     * them.
     *
     * @param mixed  $value Keyword operand to check.
     * @param string $path  Schema JSON Pointer to the operand.
     *
     * @throws SchemaAdmissionException `invalid-keyword-value` at the first
     *                                  unknown, duplicate, or misshapen name.
     *
     * @since   0.1.0
     */
    private function visitType(mixed $value, string $path): void
    {
        if (is_string($value)) {
            if (!isset(self::TYPE_NAMES[$value])) {
                throw new SchemaAdmissionException(
                    'invalid-keyword-value',
                    $path,
                    self::displayPath($path) . ' names an unknown JSON Schema type.'
                );
            }

            return;
        }
        if (!is_array($value) || !array_is_list($value) || $value === [] || count($value) > 7) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must be a type name or a non-empty array of at most seven names.'
            );
        }
        $names = [];
        foreach ($value as $index => $name) {
            if (!is_string($name) || !isset(self::TYPE_NAMES[$name]) || isset($names[$name])) {
                throw new SchemaAdmissionException(
                    'invalid-keyword-value',
                    self::appendPointer($path, (string) $index),
                    self::displayPath($path) . ' must list unique, known JSON Schema type names.'
                );
            }
            $names[$name] = true;
        }
    }

    /**
     * Check a bounded JSON value operand: finite scalars, dense bounded
     * arrays, safe bounded maps, with the depth ceiling checked before
     * entering either container.
     *
     * @param mixed  $value Value at this position.
     * @param string $path  Schema JSON Pointer to the position.
     * @param int    $depth JSON nesting depth of the position, top level = 1.
     *
     * @throws SchemaAdmissionException At the value's first failure.
     *
     * @since   0.1.0
     */
    private function visitJsonValue(mixed $value, string $path, int $depth): void
    {
        if (
            $value === null
            || is_bool($value)
            || is_string($value)
            || is_int($value)
            || (is_float($value) && is_finite($value))
        ) {
            return;
        }
        if ($depth > self::LIMITS['maxJsonDepth']) {
            throw new SchemaAdmissionException(
                'limit-exceeded',
                $path,
                self::displayPath($path) . ' exceeds the Studio Schema Profile JSON depth limit.'
            );
        }
        if (is_array($value)) {
            if (!array_is_list($value)) {
                throw new SchemaAdmissionException(
                    'invalid-keyword-value',
                    $path,
                    self::displayPath($path) . ' must be a dense JSON array.'
                );
            }
            if (count($value) > self::LIMITS['maxJsonItems']) {
                throw new SchemaAdmissionException(
                    'limit-exceeded',
                    $path,
                    sprintf('%s exceeds %d JSON items.', self::displayPath($path), self::LIMITS['maxJsonItems'])
                );
            }
            foreach ($value as $index => $entry) {
                $this->visitJsonValue($entry, self::appendPointer($path, (string) $index), $depth + 1);
            }

            return;
        }
        if ($value instanceof \stdClass) {
            $this->trackObject($value, $path);
            $names = self::memberNames($value);
            if (count($names) > self::LIMITS['maxJsonProperties']) {
                throw new SchemaAdmissionException(
                    'limit-exceeded',
                    $path,
                    sprintf(
                        '%s exceeds %d JSON properties.',
                        self::displayPath($path),
                        self::LIMITS['maxJsonProperties']
                    )
                );
            }
            usort($names, CodeUnitOrder::compare(...));
            foreach ($names as $name) {
                self::assertSafeObjectKey($name, $path);
                $this->visitJsonValue($value->{$name}, self::appendPointer($path, $name), $depth + 1);
            }

            return;
        }
        throw new SchemaAdmissionException(
            'invalid-keyword-value',
            $path,
            self::displayPath($path) . ' is not JSON-compatible.'
        );
    }

    /**
     * Refuse an object that appears twice in one document.
     *
     * @param object $value Object encountered at this position.
     * @param string $path  Schema JSON Pointer to the position, for the
     *                      refusal.
     *
     * @throws SchemaAdmissionException `invalid-root` on an aliased or
     *                                  cyclic object.
     *
     * @since   0.1.0
     */
    private function trackObject(object $value, string $path): void
    {
        if ($this->seen->offsetExists($value)) {
            throw new SchemaAdmissionException(
                'invalid-root',
                $path,
                self::displayPath($path) . ' reuses or cycles a JSON object.'
            );
        }
        $this->seen->offsetSet($value, true);
    }

    /**
     * Prove the local reference graph acyclic and every target a schema
     * position.
     *
     * The graph walk mirrors the admission grammar's child edges, resolves
     * each portable `$ref` against the document, and rejects every reference
     * edge inside a strongly connected component. Diagnostics are arbitrated
     * by the published path order, and only failures at positions the
     * bounded grammar would report stay eligible.
     *
     * @param \stdClass $root Schema document root the references resolve
     *                        against.
     *
     * @throws SchemaAdmissionException `recursive-schema` or
     *                                  `invalid-reference`, first in the
     *                                  published path order.
     *
     * @since   0.1.0
     */
    private function assertNonRecursive(\stdClass $root): void
    {
        $failures = [];
        /** @var \SplObjectStorage<\stdClass, int> $indexes */
        $indexes = new \SplObjectStorage();
        $adjacency = [];
        $reverse = [];
        $referenceSites = [];
        $expanded = new \SplObjectStorage();
        $eligibleReferences = 0;

        $ensureNode = static function (\stdClass $node) use ($indexes, &$adjacency, &$reverse): int {
            if ($indexes->offsetExists($node)) {
                return $indexes[$node];
            }
            $index = count($adjacency);
            $indexes[$node] = $index;
            $adjacency[] = [];
            $reverse[] = [];

            return $index;
        };
        $connect = static function (int $source, int $target) use (&$adjacency, &$reverse): void {
            $adjacency[$source][] = $target;
            $reverse[$target][] = $source;
        };

        $ensureNode($root);
        $stack = [['depth' => 1, 'eligible' => true, 'node' => $root, 'path' => '']];
        while ($stack !== []) {
            $frame = array_pop($stack);
            $node = $frame['node'];
            if ($expanded->offsetExists($node)) {
                continue;
            }
            $expanded->offsetSet($node, true);
            $source = $ensureNode($node);
            $children = [];
            $addChild = static function (
                mixed $value,
                string $path,
                bool $eligible
            ) use (
                &$children,
                $ensureNode,
                $connect,
                $source,
                $frame
            ): void {
                if (!$value instanceof \stdClass) {
                    return;
                }
                $target = $ensureNode($value);
                $connect($source, $target);
                $depth = $frame['depth'] + 1;
                $children[] = [
                    'depth' => $depth,
                    'eligible' => $eligible && $depth <= self::LIMITS['maxSchemaDepth'],
                    'node' => $value,
                    'path' => $path,
                ];
            };

            foreach ($this->boundedSchemaEntries($node) as $keyword) {
                $operand = $node->{$keyword};
                $keywordPath = self::appendPointer($frame['path'], $keyword);
                switch ($keyword) {
                    case '$defs':
                    case 'properties':
                        if ($operand instanceof \stdClass) {
                            $names = self::memberNames($operand);
                            $eligible = count($names) <= self::LIMITS['maxSchemaMapProperties'];
                            if ($eligible) {
                                usort($names, CodeUnitOrder::compare(...));
                            }
                            foreach ($names as $name) {
                                $addChild(
                                    $operand->{$name},
                                    self::appendPointer($keywordPath, $name),
                                    $frame['eligible'] && $eligible
                                );
                            }
                        }
                        break;
                    case '$ref':
                        if (self::isPortableLocalReference($operand)) {
                            $eligibleReferences++;
                            $reports = $frame['eligible']
                                && $eligibleReferences <= self::LIMITS['maxReferences'];
                            $referencePath = $reports ? $keywordPath : '';
                            try {
                                [$isSchemaPosition, $targetValue] =
                                    self::resolveLocalReference($root, $operand, $referencePath);
                                if (!$isSchemaPosition) {
                                    if ($reports) {
                                        $failures[] = new SchemaAdmissionException(
                                            'invalid-reference',
                                            $referencePath,
                                            sprintf(
                                                'Local schema reference %s does not resolve to a schema position.',
                                                $operand
                                            )
                                        );
                                    }
                                } elseif ($targetValue instanceof \stdClass) {
                                    $targetIndex = $ensureNode($targetValue);
                                    $connect($source, $targetIndex);
                                    if ($reports) {
                                        $referenceSites[] = [
                                            'path' => $referencePath,
                                            'source' => $source,
                                            'target' => $targetIndex,
                                        ];
                                    }
                                }
                            } catch (SchemaAdmissionException $failure) {
                                if ($reports) {
                                    $failures[] = $failure;
                                }
                            }
                        }
                        break;
                    case 'additionalProperties':
                    case 'else':
                    case 'if':
                    case 'items':
                    case 'not':
                    case 'propertyNames':
                    case 'then':
                        $addChild($operand, $keywordPath, $frame['eligible']);
                        break;
                    case 'allOf':
                    case 'anyOf':
                    case 'oneOf':
                    case 'prefixItems':
                        if (is_array($operand)) {
                            $eligible = $operand !== []
                                && count($operand) <= self::LIMITS['maxAlternatives']
                                && array_is_list($operand);
                            foreach ($operand as $index => $member) {
                                $addChild(
                                    $member,
                                    self::appendPointer($keywordPath, (string) $index),
                                    $frame['eligible'] && $eligible
                                );
                            }
                        }
                        break;
                    default:
                        break;
                }
            }
            for ($index = count($children) - 1; $index >= 0; $index--) {
                $stack[] = $children[$index];
            }
        }

        $components = self::stronglyConnectedComponents($adjacency, $reverse);
        foreach ($referenceSites as $site) {
            if ($components[$site['source']] === $components[$site['target']]) {
                $failures[] = new SchemaAdmissionException(
                    'recursive-schema',
                    $site['path'],
                    'Recursive contributed schemas are not admitted by the profile.'
                );
            }
        }

        $failure = self::firstFailure($root, $failures);
        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * Hold the root to `additionalProperties: false` and `type: "object"`,
     * the two invariants participating at their virtual member positions in
     * the published order: `additionalProperties` sorts before `type`.
     *
     * @param \stdClass $root Schema document root to check.
     *
     * @throws SchemaAdmissionException `invalid-root` at the first missing
     *                                  invariant.
     *
     * @since   0.1.0
     */
    private static function assertClosedObjectRoot(\stdClass $root): void
    {
        if (!property_exists($root, 'additionalProperties') || $root->additionalProperties !== false) {
            throw new SchemaAdmissionException(
                'invalid-root',
                '/additionalProperties',
                'Studio property schema root must declare additionalProperties: false.'
            );
        }
        if (($root->type ?? null) !== 'object') {
            throw new SchemaAdmissionException(
                'invalid-root',
                '/type',
                'Studio property schema root must declare exactly type "object".'
            );
        }
    }

    /**
     * Pick the first failure under the published diagnostic order.
     *
     * @param \stdClass                      $root     Schema document the
     *                                                 paths navigate.
     * @param list<SchemaAdmissionException> $failures Candidate refusals.
     *
     * @return SchemaAdmissionException|null The earliest refusal, or null
     *                                       when there is none.
     *
     * @since   0.1.0
     */
    private static function firstFailure(\stdClass $root, array $failures): ?SchemaAdmissionException
    {
        $first = null;
        foreach ($failures as $failure) {
            if (
                $first === null
                || self::comparePaths($root, $failure->schemaPath(), $first->schemaPath()) < 0
            ) {
                $first = $failure;
            }
        }

        return $first;
    }

    /**
     * Compare two schema pointers in the order admission visits them,
     * navigating the document to tell array token order from object token
     * order.
     *
     * @param \stdClass $root  Schema document the pointers navigate.
     * @param string    $left  Schema JSON Pointer.
     * @param string    $right Schema JSON Pointer.
     *
     * @return int Negative, zero, or positive as $left is visited before,
     *             with, or after $right.
     *
     * @since   0.1.0
     */
    private static function comparePaths(\stdClass $root, string $left, string $right): int
    {
        $leftTokens = self::pointerTokens($left);
        $rightTokens = self::pointerTokens($right);
        $parent = $root;
        $shared = min(count($leftTokens), count($rightTokens));
        for ($index = 0; $index < $shared; $index++) {
            $leftToken = $leftTokens[$index];
            $rightToken = $rightTokens[$index];
            if ($leftToken !== $rightToken) {
                if (is_array($parent)) {
                    $leftIndex = filter_var($leftToken, FILTER_VALIDATE_INT);
                    $rightIndex = filter_var($rightToken, FILTER_VALIDATE_INT);
                    if (is_int($leftIndex) && is_int($rightIndex)) {
                        return $leftIndex <=> $rightIndex;
                    }
                }

                return CodeUnitOrder::compare($leftToken, $rightToken);
            }
            if ($parent instanceof \stdClass && property_exists($parent, $leftToken)) {
                $parent = $parent->{$leftToken};
            } elseif (is_array($parent) && array_key_exists((int) $leftToken, $parent)) {
                $parent = $parent[(int) $leftToken];
            } else {
                $parent = null;
            }
        }

        return count($leftTokens) <=> count($rightTokens);
    }

    /**
     * Split one JSON Pointer into unescaped tokens; the empty pointer has
     * none.
     *
     * @param string $pointer JSON Pointer to split.
     *
     * @return list<string>
     *
     * @since   0.1.0
     */
    private static function pointerTokens(string $pointer): array
    {
        if ($pointer === '') {
            return [];
        }
        $tokens = [];
        foreach (explode('/', substr($pointer, 1)) as $token) {
            $tokens[] = str_replace(['~1', '~0'], ['/', '~'], $token);
        }

        return $tokens;
    }

    /**
     * Hold one member name to the safety and length rules; a failure points
     * at the member position unless a rejection path is given.
     *
     * @param string      $key           Member name to check.
     * @param string      $path          Schema JSON Pointer to the holding
     *                                   object, for the message.
     * @param string|null $rejectionPath Pointer a refusal carries instead of
     *                                   the member position.
     *
     * @throws SchemaAdmissionException `limit-exceeded` past the key-length
     *                                  ceiling; `unsafe-member` for an empty,
     *                                  prototype-polluting, or
     *                                  control-character name.
     *
     * @since   0.1.0
     */
    private static function assertSafeObjectKey(string $key, string $path, ?string $rejectionPath = null): void
    {
        $rejectionPath ??= self::appendPointer($path, $key);
        if (mb_strlen($key, 'UTF-8') > self::LIMITS['maxObjectKeyLength']) {
            throw new SchemaAdmissionException(
                'limit-exceeded',
                $rejectionPath,
                sprintf(
                    '%s contains an object member name longer than %d characters.',
                    self::displayPath($path),
                    self::LIMITS['maxObjectKeyLength']
                )
            );
        }
        if (
            $key === ''
            || $key === '__proto__'
            || $key === 'constructor'
            || $key === 'prototype'
            || preg_match('/[\x00-\x1F\x7F]/', $key) === 1
        ) {
            throw new SchemaAdmissionException(
                'unsafe-member',
                $rejectionPath,
                sprintf('%s contains forbidden object member name "%s".', self::displayPath($path), $key)
            );
        }
    }

    /**
     * Resolve one local reference through the profile's position grammar,
     * reporting whether the target is a schema position and what it holds.
     *
     * @param \stdClass $root      Schema document root to resolve against.
     * @param string    $reference Portable local reference: `#` or a `#/`
     *                             pointer.
     * @param string    $path      Schema JSON Pointer a refusal carries.
     *
     * @return array{0: bool, 1: mixed} Whether the target is a schema
     *                                  position, and the target value.
     *
     * @throws SchemaAdmissionException `invalid-reference` when the pointer
     *                                  does not land on a boolean or object.
     *
     * @since   0.1.0
     */
    private static function resolveLocalReference(\stdClass $root, string $reference, string $path): array
    {
        if ($reference === '#') {
            return [true, $root];
        }
        $current = $root;
        $position = 'schema';
        foreach (explode('/', substr($reference, 2)) as $encodedToken) {
            $token = str_replace(['~1', '~0'], ['/', '~'], $encodedToken);
            $nextPosition = 'other';
            if ($position === 'schema' && $current instanceof \stdClass) {
                $nextPosition = match ($token) {
                    '$defs', 'properties' => 'schema-map',
                    'additionalProperties', 'else', 'if', 'items', 'not', 'propertyNames', 'then' => 'schema',
                    'allOf', 'anyOf', 'oneOf', 'prefixItems' => 'schema-array',
                    default => 'other',
                };
            } elseif ($position === 'schema-map' && $current instanceof \stdClass) {
                $nextPosition = 'schema';
            } elseif ($position === 'schema-array' && is_array($current)) {
                $nextPosition = 'schema';
            }
            if ($current instanceof \stdClass && property_exists($current, $token)) {
                $current = $current->{$token};
            } elseif (
                is_array($current)
                && filter_var($token, FILTER_VALIDATE_INT) !== false
                && array_key_exists((int) $token, $current)
            ) {
                $current = $current[(int) $token];
            } else {
                throw new SchemaAdmissionException(
                    'invalid-reference',
                    $path,
                    sprintf('Local schema reference %s does not resolve to a schema.', $reference)
                );
            }
            $position = $nextPosition;
        }
        if (!is_bool($current) && !$current instanceof \stdClass) {
            throw new SchemaAdmissionException(
                'invalid-reference',
                $path,
                sprintf('Local schema reference %s does not resolve to a schema.', $reference)
            );
        }

        return [$position === 'schema', $current];
    }

    /**
     * Resolve every `$ref` of an admitted document for the interpreter.
     *
     * @param \stdClass $root Admitted schema document root.
     *
     * @return \SplObjectStorage<\stdClass, \stdClass|bool> Resolved target
     *                                                      per referring node.
     *
     * @since   0.1.0
     */
    private static function resolveReferences(\stdClass $root): \SplObjectStorage
    {
        /** @var \SplObjectStorage<\stdClass, \stdClass|bool> $references */
        $references = new \SplObjectStorage();
        $queue = [$root];
        $visited = new \SplObjectStorage();
        while ($queue !== []) {
            $node = array_shift($queue);
            if ($visited->offsetExists($node)) {
                continue;
            }
            $visited->offsetSet($node, true);
            foreach (get_object_vars($node) as $keyword => $operand) {
                switch ((string) $keyword) {
                    case '$ref':
                        if (is_string($operand)) {
                            [, $target] = self::resolveLocalReference($root, $operand, '');
                            if (is_bool($target) || $target instanceof \stdClass) {
                                $references[$node] = $target;
                            }
                        }
                        break;
                    case '$defs':
                    case 'properties':
                        if ($operand instanceof \stdClass) {
                            foreach (get_object_vars($operand) as $member) {
                                if ($member instanceof \stdClass) {
                                    $queue[] = $member;
                                }
                            }
                        }
                        break;
                    case 'additionalProperties':
                    case 'else':
                    case 'if':
                    case 'items':
                    case 'not':
                    case 'propertyNames':
                    case 'then':
                        if ($operand instanceof \stdClass) {
                            $queue[] = $operand;
                        }
                        break;
                    case 'allOf':
                    case 'anyOf':
                    case 'oneOf':
                    case 'prefixItems':
                        if (is_array($operand)) {
                            foreach ($operand as $member) {
                                if ($member instanceof \stdClass) {
                                    $queue[] = $member;
                                }
                            }
                        }
                        break;
                    default:
                        break;
                }
            }
        }

        return $references;
    }

    /**
     * Kosaraju's strongly connected components over the reference graph.
     *
     * @param  list<list<int>> $adjacency
     * @param  list<list<int>> $reverse
     * @return list<int> Component number per node.
     *
     * @since   0.1.0
     */
    private static function stronglyConnectedComponents(array $adjacency, array $reverse): array
    {
        $count = count($adjacency);
        $visited = array_fill(0, $count, false);
        $finishOrder = [];
        for ($start = 0; $start < $count; $start++) {
            if ($visited[$start]) {
                continue;
            }
            $visited[$start] = true;
            $stack = [['node' => $start, 'edge' => 0]];
            while ($stack !== []) {
                $frameIndex = count($stack) - 1;
                $node = $stack[$frameIndex]['node'];
                $edge = $stack[$frameIndex]['edge'];
                $target = $adjacency[$node][$edge] ?? null;
                if ($target !== null) {
                    $stack[$frameIndex]['edge']++;
                    if (!$visited[$target]) {
                        $visited[$target] = true;
                        $stack[] = ['node' => $target, 'edge' => 0];
                    }
                } else {
                    $finishOrder[] = $node;
                    array_pop($stack);
                }
            }
        }

        $components = array_fill(0, $count, -1);
        $component = 0;
        for ($order = count($finishOrder) - 1; $order >= 0; $order--) {
            $start = $finishOrder[$order];
            if ($components[$start] !== -1) {
                continue;
            }
            $components[$start] = $component;
            $stack = [$start];
            while ($stack !== []) {
                $node = array_pop($stack);
                foreach ($reverse[$node] as $source) {
                    if ($components[$source] === -1) {
                        $components[$source] = $component;
                        $stack[] = $source;
                    }
                }
            }
            $component++;
        }

        return $components;
    }

    /**
     * Measure the canonical byte budget iteratively, before sorting any
     * untrusted map. A value the canonical form would refuse defers to the
     * structural admission passes by staying inside the budget here.
     *
     * @param \stdClass $root Untrusted schema document root to measure.
     *
     * @return bool Whether the canonical UTF-8 size stays within
     *              `maxSchemaBytes`.
     *
     * @since   0.1.0
     */
    private static function withinCanonicalByteBudget(\stdClass $root): bool
    {
        $budget = self::LIMITS['maxSchemaBytes'];
        $bytes = 0;
        $stack = [$root];
        while ($stack !== []) {
            $value = array_pop($stack);
            if ($value === null) {
                $bytes += 4;
            } elseif (is_bool($value)) {
                $bytes += $value ? 4 : 5;
            } elseif (is_int($value) || is_float($value)) {
                if (is_float($value) && !is_finite($value)) {
                    return true;
                }
                $bytes += strlen(CanonicalJson::stringify($value));
            } elseif (is_string($value)) {
                $bytes += self::canonicalStringBytes($value);
            } elseif (is_array($value)) {
                if (!array_is_list($value)) {
                    return true;
                }
                $bytes += 2 + max(0, count($value) - 1);
                foreach ($value as $member) {
                    $stack[] = $member;
                }
            } elseif ($value instanceof \stdClass) {
                $members = get_object_vars($value);
                $bytes += 2 + max(0, count($members) - 1);
                foreach ($members as $name => $member) {
                    $bytes += self::canonicalStringBytes((string) $name) + 1;
                    $stack[] = $member;
                }
            } else {
                return true;
            }
            if ($bytes > $budget) {
                return false;
            }
        }

        return true;
    }

    /**
     * Count the canonical encoded bytes of one string without materialising
     * the escape: ECMA-404 minimal escaping over the raw UTF-8 bytes.
     *
     * @param string $value Raw UTF-8 text to measure.
     *
     * @return int Encoded byte count, surrounding quotes included.
     *
     * @since   0.1.0
     */
    private static function canonicalStringBytes(string $value): int
    {
        $bytes = 2;
        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            $code = ord($value[$index]);
            $bytes += match (true) {
                $code === 0x22, $code === 0x5C, $code === 0x08, $code === 0x09,
                $code === 0x0A, $code === 0x0C, $code === 0x0D => 2,
                $code <= 0x1F => 6,
                default => 1,
            };
        }

        return $bytes;
    }

    /**
     * List a node's members to visit: every key when the closed set could
     * hold them all, otherwise the allowed keys plus the first disallowed
     * one, sorted by code unit — the bound that keeps hostile wide nodes
     * cheap while preserving the published first diagnostic.
     *
     * @param \stdClass $value Schema node whose members are listed.
     *
     * @return list<string>
     *
     * @since   0.1.0
     */
    private function boundedSchemaEntries(\stdClass $value): array
    {
        $keys = self::memberNames($value);
        if (count($keys) <= count(self::ALLOWED_KEYWORDS)) {
            usort($keys, CodeUnitOrder::compare(...));

            return $keys;
        }

        $candidates = [];
        $firstInvalid = null;
        foreach ($keys as $key) {
            if (isset(self::ALLOWED_KEYWORDS[$key])) {
                $candidates[] = $key;
            } elseif ($firstInvalid === null || CodeUnitOrder::compare($key, $firstInvalid) < 0) {
                $firstInvalid = $key;
            }
        }
        if ($firstInvalid !== null) {
            $candidates[] = $firstInvalid;
        }
        usort($candidates, CodeUnitOrder::compare(...));

        return $candidates;
    }

    /**
     * List an object's member names as strings, in insertion order.
     *
     * @param \stdClass $value Decoded JSON object.
     *
     * @return list<string>
     *
     * @since   0.1.0
     */
    private static function memberNames(\stdClass $value): array
    {
        return array_map('strval', array_keys(get_object_vars($value)));
    }

    /**
     * Check a `$schema` operand against the one admissible dialect,
     * JSON Schema Draft 2020-12.
     *
     * @param mixed  $value Keyword operand to check.
     * @param string $path  Schema JSON Pointer to the operand.
     *
     * @throws SchemaAdmissionException `invalid-keyword-value` for any other
     *                                  operand.
     *
     * @since   0.1.0
     */
    private static function assertDialect(mixed $value, string $path): void
    {
        if ($value !== self::DRAFT_2020_12) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must declare JSON Schema Draft 2020-12.'
            );
        }
    }

    /**
     * Check a bounded annotation string by code-point length.
     *
     * @param mixed  $value   Keyword operand; must be a string.
     * @param string $path    Schema JSON Pointer to the operand.
     * @param int    $maximum Most code points the string may hold.
     *
     * @throws SchemaAdmissionException `invalid-keyword-value` for a
     *                                  non-string; `limit-exceeded` past the
     *                                  length ceiling.
     *
     * @since   0.1.0
     */
    private static function visitBoundedString(mixed $value, string $path, int $maximum): void
    {
        if (!is_string($value)) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must be a string.'
            );
        }
        if (mb_strlen($value, 'UTF-8') > $maximum) {
            throw new SchemaAdmissionException(
                'limit-exceeded',
                $path,
                sprintf('%s exceeds %d characters.', self::displayPath($path), $maximum)
            );
        }
    }

    /**
     * Check a non-negative integer operand, where an integral float counts
     * as an integer.
     *
     * @param mixed  $value Keyword operand to check.
     * @param string $path  Schema JSON Pointer to the operand.
     *
     * @throws SchemaAdmissionException `invalid-keyword-value` for anything
     *                                  else.
     *
     * @since   0.1.0
     */
    private static function visitNonNegativeInteger(mixed $value, string $path): void
    {
        $integral = is_int($value)
            || (is_float($value) && is_finite($value) && floor($value) === $value);
        if (!$integral || $value < 0) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must be a non-negative integer.'
            );
        }
    }

    /**
     * Check a finite number operand.
     *
     * @param mixed  $value Keyword operand to check.
     * @param string $path  Schema JSON Pointer to the operand.
     *
     * @throws SchemaAdmissionException `invalid-keyword-value` for anything
     *                                  else.
     *
     * @since   0.1.0
     */
    private static function visitFiniteNumber(mixed $value, string $path): void
    {
        if (!is_int($value) && !(is_float($value) && is_finite($value))) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must be a finite number.'
            );
        }
    }

    /**
     * Check a `multipleOf` operand: finite and strictly positive.
     *
     * @param mixed  $value Keyword operand to check.
     * @param string $path  Schema JSON Pointer to the operand.
     *
     * @throws SchemaAdmissionException `invalid-keyword-value` for anything
     *                                  else.
     *
     * @since   0.1.0
     */
    private static function visitPositiveNumber(mixed $value, string $path): void
    {
        self::visitFiniteNumber($value, $path);
        if ($value <= 0) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must be greater than zero.'
            );
        }
    }

    /**
     * Check a boolean operand.
     *
     * @param mixed  $value Keyword operand to check.
     * @param string $path  Schema JSON Pointer to the operand.
     *
     * @throws SchemaAdmissionException `invalid-keyword-value` for anything
     *                                  else.
     *
     * @since   0.1.0
     */
    private static function visitBoolean(mixed $value, string $path): void
    {
        if (!is_bool($value)) {
            throw new SchemaAdmissionException(
                'invalid-keyword-value',
                $path,
                self::displayPath($path) . ' must be a boolean.'
            );
        }
    }

    /**
     * Say whether a value is a portable bounded local reference: the
     * grammar, length and control-character rules all hold.
     *
     * @param mixed $value Candidate `$ref` operand.
     *
     * @phpstan-assert-if-true string $value
     *
     * @since   0.1.0
     */
    private static function isPortableLocalReference(mixed $value): bool
    {
        return is_string($value)
            && mb_strlen($value, 'UTF-8') <= self::LIMITS['maxReferenceLength']
            && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1
            && preg_match(self::REFERENCE_GRAMMAR, $value) === 1;
    }

    /**
     * Append one token to a schema JSON Pointer, escaping `~` and `/`.
     *
     * @param string $pointer Pointer to extend; the empty string is the root.
     * @param string $token   Unescaped token to append.
     *
     * @since   0.1.0
     */
    private static function appendPointer(string $pointer, string $token): string
    {
        return $pointer . '/' . str_replace(['~', '/'], ['~0', '~1'], $token);
    }

    /**
     * Render a pointer for a human-readable message: the empty pointer is
     * `schema root`.
     *
     * @param string $path Schema JSON Pointer to render.
     *
     * @since   0.1.0
     */
    private static function displayPath(string $path): string
    {
        return $path === '' ? 'schema root' : $path;
    }
}
