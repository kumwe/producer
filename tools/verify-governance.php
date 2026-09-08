<?php

/**
 * Project the existing reflection contract into the version 2 package manifests.
 *
 * The original complete API pin, Studio artifacts and runtime behavior stay intact.
 * @since 0.2.2
 */

declare(strict_types=1);

require_once __DIR__ . '/verify-api.php';

/** Build deterministic governance metadata from the already pinned reflected API. @since 0.2.2 */
function producerGovernanceDocuments(): array
{
    producerApiRegisterAutoloader();
    $legacy = producerApiManifest();
    $changelog = (string) file_get_contents(PRODUCER_API_ROOT . '/CHANGELOG.md');
    if (preg_match('/^## (?:\[)?([0-9]+\.[0-9]+\.[0-9]+)(?:\])?/m', $changelog, $record) !== 1) {
        throw new RuntimeException('A stable changelog release record is required.');
    }
    $release = $record[1];
    $symbols = [];
    $extensionPoints = [];
    $groups = [];
    $docs = "# Producer public API\n\n";
    $docs .= "The 70 exported types below are generated from the existing reflection contract.\n";
    $docs .= "resources/public-api.json retains the complete legacy signature and default-value pin.\n";
    $docs .= "The canonical version 2 projection is resources/public-api/v1.json.\n\n";
    $docs .= "Construct services explicitly with the host ports described in docs/host-agreement.md.\n";
    $docs .= "Producer supplies no authority, storage, ambient service container or runtime Composer dependencies.\n\n";
    foreach ($legacy['types'] as $name => $type) {
        $constants = [];
        foreach ($type['constants'] as $key => $constant) {
            $constants[$key] = ['type' => $constant['type'] ?? null];
        }
        $properties = [];
        foreach ($type['properties'] as $key => $property) {
            $properties[$key] = [
                'type' => $property['type'], 'static' => $property['static'],
                'readonly' => $property['readonly'],
            ];
        }
        $methods = [];
        $docs .= "## " . $name . "\n\n";
        $docs .= ucfirst($type['kind']) . "; source: " . $type['file'] . ".\n\n";
        foreach ($type['methods'] as $key => $method) {
            $parameters = [];
            $signature = [];
            foreach ($method['parameters'] as $parameter) {
                $parameters[] = array_intersect_key($parameter, array_flip([
                    'name', 'type', 'optional', 'variadic', 'by_reference',
                ]));
                $signature[] = ($parameter['type'] ?? 'mixed') . ' '
                    . ($parameter['by_reference'] ? '&' : '') . ($parameter['variadic'] ? '...' : '')
                    . '$' . $parameter['name'] . ($parameter['optional'] ? ' (optional)' : '');
            }
            $methods[$key] = [
                'visibility' => $method['visibility'], 'static' => $method['static'],
                'parameters' => $parameters, 'return' => $method['return_type'],
            ];
            $docs .= '- ' . $key . '(' . implode(', ', $signature) . '): '
                . ($method['return_type'] ?? 'no declared return type') . "\n";
        }
        foreach ($properties as $key => $property) {
            $docs .= '- Property $' . $key . ': ' . ($property['type'] ?? 'mixed') . ".\n";
        }
        foreach ($constants as $key => $constant) {
            $docs .= '- Constant ' . $key . ': ' . ($constant['type'] ?? 'untyped') . ".\n";
        }
        if (isset($type['enum'])) {
            $docs .= '- Enum cases and backed values: ' . json_encode($type['enum'], JSON_THROW_ON_ERROR) . ".\n";
        }
        $docs .= "\n";
        $symbols[$name] = [
            'kind' => $type['kind'], 'stability' => 'stable', 'file' => $type['file'],
            'abstract' => $type['abstract'], 'final' => $type['final'], 'readonly' => $type['readonly'],
            'parent' => $type['parent'], 'interfaces' => $type['interfaces'],
            'constants' => (object) $constants, 'properties' => (object) $properties,
            'methods' => (object) $methods, 'deprecated' => null,
        ];
        if ($type['kind'] === 'interface') {
            $extensionPoints[] = $name;
        }
        $group = explode('\\', substr($name, strlen(PRODUCER_API_PREFIX)))[0];
        $groups[$group][] = $name;
    }
    $descriptions = [
        'Canonical' => 'Deterministic Studio wire serialization and explicit canonical refusals.',
        'Error' => 'Typed diagnostics, stable error taxonomy and explicit contract refusals.',
        'Schema' => 'Pinned schema admission and bounded Studio document validation.',
        'Css' => 'Validated design-token values and deterministic stylesheet emission.',
        'Drawing' => 'Bounded typed drawing values with deterministic rendering.',
        'Render' => 'Bounded semantic HTML and CSS results from validated published compositions.',
        'Renderer' => 'Bounded semantic HTML and CSS results from validated published compositions.',
        'Studio' => 'Pinned Studio schemas, assets and contract resources with digest verification.',
        'Token' => 'Typed design-token values and deterministic CSS declarations.',
        'Wire' => 'Typed authoring transport, validation and explicit host authority and persistence ports.',
    ];
    $capabilities = [];
    foreach ($groups as $group => $names) {
        if (!isset($descriptions[$group])) {
            throw new RuntimeException('Document the newly exported capability group: ' . $group);
        }
        $capabilities[] = [
            'id' => 'producer.' . strtolower($group), 'title' => 'Producer ' . $group,
            'description' => $descriptions[$group], 'symbols' => $names,
            'documentation' => ['docs/public-api.md', 'docs/host-agreement.md', 'docs/host-guide.md'],
        ];
    }
    return [
        'resources/public-api/v1.json' => [
            'schema' => 'kumwe-package-public-api/v1', 'package' => 'kumwe/producer',
            'release' => $release, 'namespace' => PRODUCER_API_PREFIX, 'symbols' => (object) $symbols,
            'extension_points' => $extensionPoints, 'digest_of' => 'src/',
        ],
        'resources/capabilities/v1.json' => [
            'schema' => 'kumwe-package-capabilities/v1', 'package' => 'kumwe/producer',
            'release' => $release, 'namespace' => PRODUCER_API_PREFIX,
            'responsibility' => 'Implement the pinned Studio contract through portable PHP wire handling and rendering.',
            'non_responsibilities' => [
                'Host authentication, authorization and final authority.',
                'Persistence, revisions, replay storage, transport and application lifecycle.',
                'JavaScript compilation, dynamic script generation and runtime Composer dependencies.',
            ],
            'capabilities' => $capabilities, 'native_requirements' => null, 'deprecations' => [],
        ],
        'resources/service-map/v1.json' => [
            'schema' => 'kumwe-package-service-map/v1', 'package' => 'kumwe/producer',
            'release' => $release, 'config_provider' => null,
            'provider_absence_reason' => 'The host explicitly constructs portable services with its own ports; '
                . 'the charter forbids runtime Composer dependencies, authority and ambient container discovery.',
            'factories' => [], 'aliases' => (object) [], 'delegators' => [], 'configuration_keys' => [],
        ],
        'docs/public-api.md' => $docs,
    ];
}

$write = ($argv[1] ?? null) === '--write';
if (count($argv) > 2 || (isset($argv[1]) && !$write)) {
    throw new RuntimeException('Usage: verify-governance.php [--write]');
}
foreach (producerGovernanceDocuments() as $path => $document) {
    $bytes = is_string($document) ? $document
        : json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $target = PRODUCER_API_ROOT . '/' . $path;
    if ($write) {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        file_put_contents($target, $bytes);
    } elseif (!is_file($target) || file_get_contents($target) !== $bytes) {
        throw new RuntimeException('Governance drift: review and regenerate ' . $path);
    }
}
if (!$write) {
    require __DIR__ . '/verify-governed-manifests.php';
}
echo "Producer canonical manifests and complete public API documentation agree with reflected source.\n";
