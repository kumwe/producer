<?php

/** Validate shipped governance contracts without a consumer checkout or runtime dependency. @since 0.2.2 */

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn (string $path): array => json_decode(
    file_get_contents($root . '/' . $path),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
$composer = $read('composer.json');
$api = $read('resources/public-api/v1.json');
$handoff = file_get_contents($root . '/MIGRATION-HANDOFF.md');
$apiSchema = $read('tools/schemas/package-public-api.v1.schema.json');
contractSchema(json_decode(file_get_contents($root . '/resources/public-api/v1.json')), $apiSchema, $apiSchema, 'public API');
foreach (['capabilities', 'service-map'] as $name) {
    $path = 'resources/' . $name . '/v1.json';
    $manifest = $read($path);
    $schema = $read('tools/schemas/package-' . $name . '.v1.schema.json');
    contractSchema(json_decode(file_get_contents($root . "/" . $path), false, 512, JSON_THROW_ON_ERROR), $schema, $schema, $path);
    if ($manifest['package'] !== $composer['name'] || $manifest['release'] !== $api['release']) {
        throw new RuntimeException($path . ': package/release differs from the canonical API.');
    }
    if ($name === 'capabilities') {
        if (!array_key_exists($manifest['namespace'], $composer['autoload']['psr-4'])) {
            throw new RuntimeException('Capability namespace differs from Composer PSR-4.');
        }
        $ids = [];
        foreach ($manifest['capabilities'] as $capability) {
            if (isset($ids[$capability['id']])) {
                throw new RuntimeException('Duplicate capability id: ' . $capability['id']);
            }
            $ids[$capability['id']] = true;
            foreach ($capability['symbols'] as $symbol) {
                if (!isset($api['symbols'][$symbol])) {
                    throw new RuntimeException('Capability references a non-exported symbol: ' . $symbol);
                }
            }
            foreach ($capability['documentation'] as $document) {
                if (str_contains($document, '..') || !is_file($root . '/' . $document)) {
                    throw new RuntimeException('Capability documentation is not shipped: ' . $document);
                }
            }
        }
    } else {
        $provider = $manifest['config_provider'];
        if ($provider === null) {
            if ($manifest['factories'] !== [] || trim($manifest['provider_absence_reason'] ?? '') === '') {
                throw new RuntimeException('Direct construction must explain provider absence and have no factories.');
            }
        } else {
            require_once $root . '/vendor/autoload.php';
            if (!isset($api['symbols'][$provider]) || $manifest['provider_absence_reason'] !== null) {
                throw new RuntimeException('Service provider must be exported and cannot claim absence.');
            }
            $dependencies = (new $provider())()['dependencies'];
            $factories = [];
            foreach ($manifest['factories'] as $factory) {
                if (!isset($api['symbols'][$factory['service']], $api['symbols'][$factory['factory']])) {
                    throw new RuntimeException('Service/factory must belong to the exported public API.');
                }
                $factories[$factory['service']] = $factory['factory'];
                $shared = $dependencies['shared'][$factory['service']] ?? true;
                if ($shared !== ($factory['lifetime'] === 'shared')) {
                    throw new RuntimeException('Service lifetime contradicts the actual ConfigProvider.');
                }
            }
            if ($factories !== $dependencies['factories']) {
                throw new RuntimeException('Service map factories contradict the actual ConfigProvider.');
            }
        }
    }
}
foreach (['capabilities', 'service-map', 'public-api'] as $name) {
    $path = 'resources/' . $name . '/v1.json';
    $pattern = '~path: "?' . preg_quote($path, '~') . '"?\s+sha256: "?'
        . hash_file('sha256', $root . '/' . $path) . '"?(?:\s|$)~';
    if (preg_match($pattern, $handoff) !== 1) {
        throw new RuntimeException('Handoff manifest digest is absent or stale: ' . $path);
    }
}
$sections = [
    'Migration/implementation summary', 'Public API and responsibility',
    'Capability reuse/semantic input review', 'Consumer inventory', 'Test ownership',
    'Next-task execution notes', 'Drift check', 'Validation recipe and observed local results',
];
preg_match_all('/^## (.+)$/m', $handoff, $matches);
if ($matches[1] !== $sections || !str_starts_with($handoff, "---\n")) {
    throw new RuntimeException('Handoff must retain v2 front matter and the eight ordered narrative sections.');
}
echo "Governed manifest schemas, exports, documentation, provider and handoff digests verified.\n";

/** Execute the keywords in the two shipped authoritative schema snapshots; reject unsupported schema changes. */
function contractSchema(mixed $value, array $schema, array $root, string $path): void
{
    $known = [
        '$schema', '$id', '$defs', '$ref', 'title', 'description', 'type', 'properties', 'required',
        'additionalProperties', 'items', 'minItems', 'minLength', 'minimum', 'pattern', 'enum', 'const', 'anyOf',
    ];
    if (array_diff(array_keys($schema), $known) !== []) {
        throw new RuntimeException($path . ': unsupported schema keyword; extend the contract gate explicitly.');
    }
    if (isset($schema['$ref'])) {
        if (!str_starts_with($schema['$ref'], '#/$defs/')) {
            throw new RuntimeException($path . ': only local definitions are supported.');
        }
        contractSchema($value, $root['$defs'][substr($schema['$ref'], 8)], $root, $path);
        return;
    }
    if (isset($schema['anyOf'])) {
        foreach ($schema['anyOf'] as $alternative) {
            try {
                contractSchema($value, $alternative, $root, $path);
                return;
            } catch (RuntimeException) {
            }
        }
        throw new RuntimeException($path . ': does not satisfy any schema alternative.');
    }
    if (isset($schema['type'])) {
        $valid = false;
        foreach ((array) $schema['type'] as $type) {
            $valid = $valid || match ($type) {
                'object' => is_object($value),
                'array' => is_array($value) && array_is_list($value),
                'string' => is_string($value), 'integer' => is_int($value),
                'boolean' => is_bool($value), 'null' => $value === null,
                default => throw new RuntimeException($path . ': unsupported schema type.'),
            };
        }
        if (!$valid) {
            throw new RuntimeException($path . ': wrong value type.');
        }
    }
    if (array_key_exists('const', $schema) && $value !== $schema['const']) {
        throw new RuntimeException($path . ': wrong schema identity.');
    }
    if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
        throw new RuntimeException($path . ': value is outside the allowed enumeration.');
    }
    if (is_string($value)) {
        if (isset($schema['minLength']) && strlen($value) < $schema['minLength']) {
            throw new RuntimeException($path . ': text is too short.');
        }
        if (isset($schema['pattern']) && preg_match('~' . $schema['pattern'] . '~D', $value) !== 1) {
            throw new RuntimeException($path . ': text does not match the schema pattern.');
        }
    }
    if (is_int($value) && isset($schema['minimum']) && $value < $schema['minimum']) {
        throw new RuntimeException($path . ': value is below the minimum.');
    }
    if (is_object($value)) {
        $value = (array) $value;
    }
    if (!is_array($value)) {
        return;
    }
    if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
        throw new RuntimeException($path . ': array is too short.');
    }
    foreach ($schema['required'] ?? [] as $key) {
        if (!array_key_exists($key, $value)) {
            throw new RuntimeException($path . ': required field is absent: ' . $key);
        }
    }
    foreach ($value as $key => $child) {
        if (isset($schema['items'])) {
            contractSchema($child, $schema['items'], $root, $path . '/' . $key);
        } elseif (isset($schema['properties'][$key])) {
            contractSchema($child, $schema['properties'][$key], $root, $path . '/' . $key);
        } elseif (($schema['additionalProperties'] ?? true) === false) {
            throw new RuntimeException($path . ': unsupported field: ' . $key);
        } elseif (is_array($schema['additionalProperties'] ?? null)) {
            contractSchema($child, $schema['additionalProperties'], $root, $path . '/' . $key);
        }
    }
}
