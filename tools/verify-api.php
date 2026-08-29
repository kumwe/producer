<?php

/**
 * Verify the complete public PHP surface against its reviewed 0.2.0 pin.
 *
 * Reflection captures callable signatures, named parameters, constants,
 * properties, inheritance, interfaces, and enum cases. The generated document
 * contains no platform paths or timestamps, so identical source produces
 * byte-identical JSON on every supported PHP runtime.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

const PRODUCER_API_ROOT = __DIR__ . '/..';
const PRODUCER_API_SOURCE = PRODUCER_API_ROOT . '/src';
const PRODUCER_API_PREFIX = 'Kumwe\\Producer\\';
const PRODUCER_API_MANIFEST = PRODUCER_API_ROOT . '/resources/public-api.json';

/** @var list<string> $arguments */
$arguments = $_SERVER['argv'] ?? [];

try {
    exit(producerApiMain($arguments));
} catch (Throwable $error) {
    fwrite(STDERR, "Producer public API verification failed: {$error->getMessage()}\n");
    exit(1);
}

/**
 * Generate, verify, record, or negatively self-test the public surface.
 *
 * @param list<string> $arguments Command followed by no option, --write, or --self-test.
 *
 * @since 0.2.0
 */
function producerApiMain(array $arguments): int
{
    $options = array_slice($arguments, 1);
    if (!in_array($options, [[], ['--write'], ['--self-test']], true)) {
        fwrite(STDERR, "Usage: php tools/verify-api.php [--write|--self-test]\n");

        return 2;
    }

    producerApiRegisterAutoloader();
    $manifest = producerApiManifest();

    if ($options === ['--self-test']) {
        return producerApiNegativeSelfTest($manifest);
    }

    $bytes = producerApiEncode($manifest);
    if ($options === ['--write']) {
        producerApiWriteManifest($bytes);
        fwrite(
            STDOUT,
            sprintf(
                "Producer public API 0.2.0 accepted: %d canonical types written to %s.\n",
                count($manifest['types']),
                producerApiRelativePath(PRODUCER_API_MANIFEST),
            ),
        );

        return 0;
    }

    if (!is_file(PRODUCER_API_MANIFEST)) {
        fwrite(STDERR, "The Producer 0.2.0 public API has no pin; review it, then run --write.\n");

        return 1;
    }

    $expectedBytes = file_get_contents(PRODUCER_API_MANIFEST);
    if ($expectedBytes === false) {
        throw new RuntimeException('Cannot read ' . producerApiRelativePath(PRODUCER_API_MANIFEST) . '.');
    }
    if ($expectedBytes !== $bytes) {
        producerApiReportDifference($expectedBytes, $manifest);

        return 1;
    }

    fwrite(
        STDOUT,
        sprintf(
            "Producer public API 0.2.0 is current: %d canonical types and all public members are pinned.\n",
            count($manifest['types']),
        ),
    );

    return 0;
}

/**
 * Register the package's dependency-free PSR-4 loader.
 *
 * @since 0.2.0
 */
function producerApiRegisterAutoloader(): void
{
    spl_autoload_register(static function (string $class): void {
        if (!str_starts_with($class, PRODUCER_API_PREFIX)) {
            return;
        }

        $relative = substr($class, strlen(PRODUCER_API_PREFIX));
        $path = PRODUCER_API_SOURCE . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require $path;
        }
    });
}

/**
 * Render the complete deterministic package surface.
 *
 * @return array{
 *     schema: int,
 *     package: string,
 *     release: string,
 *     namespace: string,
 *     types: array<string, array<string, mixed>>
 * }
 *
 * @since 0.2.0
 */
function producerApiManifest(): array
{
    $types = [];
    foreach (producerApiTypeNames() as $name) {
        $types[$name] = producerApiType(new ReflectionClass($name));
    }
    ksort($types, SORT_STRING);

    return [
        'schema' => 2,
        'package' => 'kumwe/producer',
        'release' => '0.2.0',
        'namespace' => PRODUCER_API_PREFIX,
        'types' => $types,
    ];
}

/**
 * Discover every PSR-4 type declared by the package source tree.
 *
 * One class-like declaration per matching PSR-4 path is required. A file that
 * cannot be loaded at its expected canonical name fails closed.
 *
 * @return list<class-string>
 *
 * @since 0.2.0
 */
function producerApiTypeNames(): array
{
    if (!is_dir(PRODUCER_API_SOURCE)) {
        throw new RuntimeException('The source directory is missing.');
    }

    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(PRODUCER_API_SOURCE, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
            throw new RuntimeException('Source discovery returned an invalid filesystem entry.');
        }
        if ($file->isFile() && $file->getExtension() === 'php') {
            $paths[] = $file->getPathname();
        }
    }
    sort($paths, SORT_STRING);

    $names = [];
    foreach ($paths as $path) {
        $relative = substr($path, strlen(PRODUCER_API_SOURCE) + 1, -4);
        $name = PRODUCER_API_PREFIX . str_replace('/', '\\', $relative);
        if (
            !class_exists($name)
            && !interface_exists($name)
            && !trait_exists($name)
            && !enum_exists($name)
        ) {
            throw new RuntimeException(
                sprintf('%s does not declare its expected PSR-4 type %s.', producerApiRelativePath($path), $name),
            );
        }
        $names[] = $name;
    }

    if ($names === []) {
        throw new RuntimeException('The public API cannot be empty.');
    }

    return $names;
}

/**
 * Render one declaration and only the members it owns.
 *
 * Public members are consumer API. Protected members are included because
 * they become subclass API if a type is intentionally extensible.
 *
 * @param ReflectionClass<object> $type Reflected canonical declaration.
 *
 * @return array<string, mixed>
 *
 * @since 0.2.0
 */
function producerApiType(ReflectionClass $type): array
{
    $name = $type->getName();
    $parent = $type->getParentClass();
    $interfaces = $type->getInterfaceNames();
    sort($interfaces, SORT_STRING);
    $kind = 'class';
    if ($type->isEnum()) {
        $kind = 'enum';
    } elseif ($type->isInterface()) {
        $kind = 'interface';
    } elseif ($type->isTrait()) {
        $kind = 'trait';
    }

    $sourcePath = $type->getFileName();
    if ($sourcePath === false) {
        throw new RuntimeException("Public type {$name} has no source file.");
    }

    $manifest = [
        'file' => producerApiRelativePath($sourcePath),
        'kind' => $kind,
        'abstract' => $type->isAbstract(),
        'final' => $type->isFinal(),
        'readonly' => method_exists($type, 'isReadOnly') && $type->isReadOnly(),
        'parent' => $parent === false ? null : $parent->getName(),
        'interfaces' => $interfaces,
        'constants' => producerApiConstants($type, $name),
        'properties' => producerApiProperties($type, $name),
        'methods' => producerApiMethods($type, $name),
    ];

    if ($type->isEnum()) {
        if (!enum_exists($name)) {
            throw new RuntimeException("Reflected enum {$name} cannot be loaded.");
        }
        $manifest['enum'] = producerApiEnum(new ReflectionEnum($name));
    }

    return $manifest;
}

/**
 * Render declared public and protected constants.
 *
 * @param ReflectionClass<object> $type Reflected declaration.
 * @param string $owner Canonical declaring name.
 *
 * @return array<string, array<string, mixed>>
 *
 * @since 0.2.0
 */
function producerApiConstants(ReflectionClass $type, string $owner): array
{
    $constants = [];
    $filter = ReflectionClassConstant::IS_PUBLIC | ReflectionClassConstant::IS_PROTECTED;
    foreach ($type->getReflectionConstants($filter) as $constant) {
        if ($constant->getDeclaringClass()->getName() !== $owner || $constant->isEnumCase()) {
            continue;
        }
        $constants[$constant->getName()] = [
            'visibility' => producerApiVisibility($constant),
            'final' => $constant->isFinal(),
            // The package supports PHP 8.1, so its source cannot declare a
            // typed class constant (introduced in PHP 8.3).
            'type' => null,
            'value' => producerApiValue($constant->getValue()),
        ];
    }
    ksort($constants, SORT_STRING);

    return $constants;
}

/**
 * Render declared public and protected properties.
 *
 * @param ReflectionClass<object> $type Reflected declaration.
 * @param string $owner Canonical declaring name.
 *
 * @return array<string, array<string, mixed>>
 *
 * @since 0.2.0
 */
function producerApiProperties(ReflectionClass $type, string $owner): array
{
    $properties = [];
    $filter = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED;
    foreach ($type->getProperties($filter) as $property) {
        if ($property->getDeclaringClass()->getName() !== $owner) {
            continue;
        }
        if ($type->isEnum() && in_array($property->getName(), ['name', 'value'], true)) {
            continue;
        }
        $propertyType = $property->getType();
        $entry = [
            'visibility' => producerApiVisibility($property),
            'static' => $property->isStatic(),
            'readonly' => $property->isReadOnly(),
            'type' => $propertyType === null ? null : producerApiReflectionType($propertyType, $owner),
        ];
        if ($property->hasDefaultValue()) {
            $entry['default'] = producerApiValue($property->getDefaultValue());
        }
        $properties[$property->getName()] = $entry;
    }
    ksort($properties, SORT_STRING);

    return $properties;
}

/**
 * Render declared public and protected methods.
 *
 * @param ReflectionClass<object> $type Reflected declaration.
 * @param string $owner Canonical declaring name.
 *
 * @return array<string, array<string, mixed>>
 *
 * @since 0.2.0
 */
function producerApiMethods(ReflectionClass $type, string $owner): array
{
    $methods = [];
    $filter = ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED;
    foreach ($type->getMethods($filter) as $method) {
        if ($method->getDeclaringClass()->getName() !== $owner) {
            continue;
        }
        if ($type->isEnum() && in_array($method->getName(), ['cases', 'from', 'tryFrom'], true)) {
            continue;
        }
        $returnType = $method->getReturnType();
        $methods[$method->getName()] = [
            'visibility' => producerApiVisibility($method),
            'static' => $method->isStatic(),
            'final' => $method->isFinal(),
            'abstract' => $method->isAbstract(),
            'returns_reference' => $method->returnsReference(),
            'parameters' => array_map(
                static fn (ReflectionParameter $parameter): array => producerApiParameter($parameter, $owner),
                $method->getParameters(),
            ),
            'return_type' => $returnType === null ? null : producerApiReflectionType($returnType, $owner),
        ];
    }
    ksort($methods, SORT_STRING);

    return $methods;
}

/**
 * Render one ordered method parameter.
 *
 * @param ReflectionParameter $parameter Reflected parameter.
 * @param string $owner Canonical declaring type name.
 *
 * @return array<string, mixed>
 *
 * @since 0.2.0
 */
function producerApiParameter(ReflectionParameter $parameter, string $owner): array
{
    $type = $parameter->getType();
    $entry = [
        'name' => $parameter->getName(),
        'type' => $type === null ? null : producerApiReflectionType($type, $owner),
        'by_reference' => $parameter->isPassedByReference(),
        'variadic' => $parameter->isVariadic(),
        'optional' => $parameter->isOptional(),
    ];
    if ($parameter->isDefaultValueAvailable()) {
        $entry['default'] = $parameter->isDefaultValueConstant()
            ? [
                'kind' => 'constant',
                'name' => producerApiConstantName($parameter->getDefaultValueConstantName(), $owner),
            ]
            : ['kind' => 'value', 'value' => producerApiValue($parameter->getDefaultValue())];
    }

    return $entry;
}

/**
 * Normalize a relative parameter-default constant to its canonical owner.
 *
 * @param ?string $name Reflected constant spelling.
 * @param string $owner Canonical declaring type name.
 *
 * @since 0.2.0
 */
function producerApiConstantName(?string $name, string $owner): ?string
{
    if ($name === null) {
        return null;
    }
    if (str_starts_with($name, 'self::')) {
        return $owner . substr($name, 4);
    }
    if (str_starts_with($name, 'parent::')) {
        $parent = get_parent_class($owner);
        if ($parent === false) {
            throw new RuntimeException("Relative parent constant on {$owner} has no parent class.");
        }

        return $parent . substr($name, 6);
    }

    return $name;
}

/**
 * Render enum backing type and observable declaration-ordered cases.
 *
 * @param ReflectionEnum<UnitEnum> $enum Reflected enum.
 *
 * @return array{backing_type: ?string, cases: list<array{name: string, value?: int|string}>}
 *
 * @since 0.2.0
 */
function producerApiEnum(ReflectionEnum $enum): array
{
    $backingType = $enum->getBackingType();
    $cases = [];
    foreach ($enum->getCases() as $case) {
        $entry = ['name' => $case->getName()];
        if ($case instanceof ReflectionEnumBackedCase) {
            $entry['value'] = $case->getBackingValue();
        }
        $cases[] = $entry;
    }

    return [
        'backing_type' => $backingType === null ? null : producerApiReflectionType($backingType, $enum->getName()),
        'cases' => $cases,
    ];
}

/**
 * Render a named, union, or intersection type without import abbreviations.
 *
 * @param ReflectionType $type Reflected declaration type.
 * @param string $owner Declaring type used to normalize self and parent.
 *
 * @since 0.2.0
 */
function producerApiReflectionType(ReflectionType $type, string $owner): string
{
    if ($type instanceof ReflectionNamedType) {
        $name = $type->getName();
        if ($name === 'self') {
            $name = $owner;
        } elseif ($name === 'parent') {
            $parent = get_parent_class($owner);
            if ($parent === false) {
                throw new RuntimeException("Relative parent type on {$owner} has no parent class.");
            }
            $name = $parent;
        }

        return $type->allowsNull() && !in_array($name, ['mixed', 'null'], true) ? '?' . $name : $name;
    }
    if ($type instanceof ReflectionUnionType) {
        return implode('|', array_map(
            static fn (ReflectionType $member): string => producerApiReflectionType($member, $owner),
            $type->getTypes(),
        ));
    }
    if ($type instanceof ReflectionIntersectionType) {
        return implode('&', array_map(
            static fn (ReflectionType $member): string => producerApiReflectionType($member, $owner),
            $type->getTypes(),
        ));
    }

    throw new RuntimeException('Unknown reflection type kind ' . $type::class . '.');
}

/**
 * Normalize a reflected value into deterministic JSON data.
 *
 * @param mixed $value Constant, property, or parameter default.
 *
 * @return mixed JSON-compatible value preserving PHP array order.
 *
 * @since 0.2.0
 */
function producerApiValue(mixed $value): mixed
{
    if ($value === null || is_scalar($value)) {
        return $value;
    }
    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $key => $member) {
            $normalized[$key] = producerApiValue($member);
        }

        return $normalized;
    }
    if (is_object($value)) {
        $state = [];
        foreach (get_object_vars($value) as $key => $member) {
            $state[$key] = producerApiValue($member);
        }

        return [
            'kind' => 'object',
            'class' => $value::class,
            'state' => $state,
        ];
    }

    throw new RuntimeException('Unsupported reflected default value of type ' . get_debug_type($value) . '.');
}

/**
 * Return a reflected member's declared visibility.
 *
 * @param ReflectionClassConstant|ReflectionProperty|ReflectionMethod $member Reflected member.
 *
 * @since 0.2.0
 */
function producerApiVisibility(
    ReflectionClassConstant|ReflectionProperty|ReflectionMethod $member,
): string {
    return $member->isPublic() ? 'public' : 'protected';
}

/**
 * Prove that a single public method-signature drift is rejected.
 *
 * @param array<string, mixed> $manifest Current canonical manifest.
 *
 * @since 0.2.0
 */
function producerApiNegativeSelfTest(array $manifest): int
{
    $drifted = $manifest;
    $changed = null;
    $types = $drifted['types'] ?? null;
    if (!is_array($types)) {
        fwrite(STDERR, "Public API negative self-test found no canonical type map.\n");

        return 1;
    }
    foreach ($types as $typeName => $type) {
        if (!is_string($typeName)) {
            continue;
        }
        if (!is_array($type)) {
            continue;
        }
        $methods = $type['methods'] ?? null;
        if (!is_array($methods) || $methods === []) {
            continue;
        }
        $methodName = array_key_first($methods);
        $method = is_string($methodName) ? ($methods[$methodName] ?? null) : null;
        if (!is_string($methodName) || !is_array($method)) {
            continue;
        }
        $method['return_type'] = '__deliberate_negative_drift__';
        $methods[$methodName] = $method;
        $type['methods'] = $methods;
        $types[$typeName] = $type;
        $changed = $typeName . '::' . $methodName . '()';
        break;
    }
    $drifted['types'] = $types;

    if ($changed === null) {
        fwrite(STDERR, "Public API negative self-test found no method to mutate.\n");

        return 1;
    }

    $difference = producerApiFirstDifference($manifest, $drifted);
    if ($difference === null || !str_contains($difference['path'], '.methods.')) {
        fwrite(STDERR, "Public API negative self-test failed to reject a method signature change.\n");

        return 1;
    }

    fwrite(STDOUT, "Producer public API negative self-test passed: member drift at {$changed} was rejected.\n");

    return 0;
}

/**
 * Encode the canonical manifest document.
 *
 * @param array<string, mixed> $manifest Reflected surface.
 *
 * @since 0.2.0
 */
function producerApiEncode(array $manifest): string
{
    return json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    ) . "\n";
}

/**
 * Atomically replace the reviewed public API pin.
 *
 * @param string $bytes Canonical manifest JSON.
 *
 * @since 0.2.0
 */
function producerApiWriteManifest(string $bytes): void
{
    $temporary = tempnam(dirname(PRODUCER_API_MANIFEST), '.public-api-');
    if ($temporary === false) {
        throw new RuntimeException('Cannot create a temporary API manifest.');
    }

    try {
        if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes)) {
            throw new RuntimeException('Cannot write the complete API manifest.');
        }
        if (!rename($temporary, PRODUCER_API_MANIFEST)) {
            throw new RuntimeException('Cannot replace the API manifest atomically.');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

/**
 * Report the first semantic difference or non-canonical formatting.
 *
 * @param string $expectedBytes Checked-in pin bytes.
 * @param array<string, mixed> $actual Generated surface.
 *
 * @since 0.2.0
 */
function producerApiReportDifference(string $expectedBytes, array $actual): void
{
    try {
        $expected = json_decode($expectedBytes, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        fwrite(STDERR, "The public API pin is not valid JSON: {$error->getMessage()}\n");

        return;
    }

    $difference = producerApiFirstDifference($expected, $actual);
    if ($difference === null) {
        fwrite(STDERR, "The public API pin is semantically current but is not canonical JSON; run --write.\n");

        return;
    }

    fwrite(
        STDERR,
        sprintf(
            "Producer public API 0.2.0 drifted at %s.\nExpected: %s\nActual:   %s\n"
                . "Treat incompatible changes as a new package major; use --write only after compatibility review.\n",
            $difference['path'],
            producerApiDisplayValue($difference['expected']),
            producerApiDisplayValue($difference['actual']),
        ),
    );
}

/**
 * Find the first semantic difference between decoded manifest values.
 *
 * @param mixed $expected Pinned value.
 * @param mixed $actual Generated value.
 * @param string $path JSONPath-like location.
 *
 * @return ?array{path: string, expected: mixed, actual: mixed}
 *
 * @since 0.2.0
 */
function producerApiFirstDifference(mixed $expected, mixed $actual, string $path = '$'): ?array
{
    if (!is_array($expected)) {
        if (is_array($actual)) {
            return ['path' => $path, 'expected' => $expected, 'actual' => $actual];
        }

        return $expected === $actual ? null : ['path' => $path, 'expected' => $expected, 'actual' => $actual];
    }
    if (!is_array($actual)) {
        return ['path' => $path, 'expected' => $expected, 'actual' => $actual];
    }

    $expectedKeys = array_keys($expected);
    $actualKeys = array_keys($actual);
    $allKeys = array_values(array_unique([...$expectedKeys, ...$actualKeys], SORT_REGULAR));
    foreach ($allKeys as $key) {
        $memberPath = is_int($key) ? $path . '[' . $key . ']' : $path . '.' . $key;
        if (!array_key_exists($key, $expected)) {
            return ['path' => $memberPath, 'expected' => '<absent>', 'actual' => $actual[$key]];
        }
        if (!array_key_exists($key, $actual)) {
            return ['path' => $memberPath, 'expected' => $expected[$key], 'actual' => '<absent>'];
        }
        $difference = producerApiFirstDifference($expected[$key], $actual[$key], $memberPath);
        if ($difference !== null) {
            return $difference;
        }
    }

    return null;
}

/**
 * Render a concise JSON-compatible difference value.
 *
 * @param mixed $value Difference member.
 *
 * @since 0.2.0
 */
function producerApiDisplayValue(mixed $value): string
{
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return $encoded === false ? var_export($value, true) : $encoded;
}

/**
 * Spell a repository path without environment-specific prefixes.
 *
 * @param string $path Absolute repository path.
 *
 * @since 0.2.0
 */
function producerApiRelativePath(string $path): string
{
    $root = realpath(PRODUCER_API_ROOT);
    $resolved = realpath($path);
    if ($root === false || $resolved === false || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Cannot normalize a path outside the Producer repository.');
    }

    return str_replace('\\', '/', substr($resolved, strlen($root) + 1));
}
