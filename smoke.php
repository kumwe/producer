<?php

/**
 * Prove the installed no-dev package and its exact Studio corpus.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

(static function (): void {
    $root = __DIR__;
    $autoload = $root . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        fwrite(STDERR, "Composer autoload is absent; run composer install first.\n");
        exit(1);
    }
    $loader = require $autoload;

    $developmentPackages = [
        $root . '/vendor/phpstan/phpstan',
        $root . '/vendor/squizlabs/php_codesniffer',
    ];
    $developmentPackageCount = count(array_filter($developmentPackages, 'is_dir'));
    if ($developmentPackageCount !== 0 && $developmentPackageCount !== count($developmentPackages)) {
        fwrite(STDERR, "The Composer development toolchain is only partly installed.\n");
        exit(1);
    }
    $noDev = $developmentPackageCount === 0;
    if (
        $noDev
        && (!$loader instanceof \Composer\Autoload\ClassLoader || !$loader->isClassMapAuthoritative())
    ) {
        fwrite(STDERR, "The no-dev package autoloader is not classmap-authoritative.\n");
        exit(1);
    }

    try {
        $snapshot = json_decode(
            (string) file_get_contents($root . '/resources/public-api.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $error) {
        fwrite(STDERR, "The public API manifest is malformed: {$error->getMessage()}\n");
        exit(1);
    }
    if (
        !is_array($snapshot)
        || ($snapshot['schema'] ?? null) !== 2
        || !is_array($snapshot['types'] ?? null)
        || count($snapshot['types']) !== 70
    ) {
        fwrite(STDERR, "The package must expose exactly 70 reviewed public types.\n");
        exit(1);
    }

    $errors = [];
    foreach ($snapshot['types'] as $type => $entry) {
        $kind = is_array($entry) ? ($entry['kind'] ?? null) : null;
        if (!is_string($type) || !is_string($kind)) {
            $errors[] = 'Malformed public API snapshot entry.';
            continue;
        }
        $loaded = match ($kind) {
            'class' => class_exists($type),
            'interface' => interface_exists($type),
            'enum' => enum_exists($type),
            default => false,
        };
        if (!$loaded) {
            $errors[] = $type . ' did not autoload as a ' . $kind . '.';
        }
    }

    $contractRoot = $root . '/resources/studio-contract';
    try {
        $schemaManifest = json_decode(
            (string) file_get_contents($contractRoot . '/protocol/schemas/manifest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $corpusManifest = json_decode(
            \Kumwe\Producer\Schema\StudioContractResources::testkitManifestBytes(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (Throwable $error) {
        $errors[] = 'The installed Studio manifests could not be read: ' . $error->getMessage();
        $schemaManifest = [];
        $corpusManifest = [];
    }

    $schemas = is_array($schemaManifest) && is_array($schemaManifest['schemas'] ?? null)
        ? $schemaManifest['schemas']
        : [];
    if (count($schemas) !== 55) {
        $errors[] = 'The package must contain exactly 55 released Studio schemas.';
    }

    $corpusFiles = [];
    $groups = is_array($corpusManifest) && is_array($corpusManifest['groups'] ?? null)
        ? $corpusManifest['groups']
        : [];
    foreach ($groups as $group) {
        $path = is_array($group) ? ($group['path'] ?? null) : null;
        $files = is_array($group) ? ($group['files'] ?? null) : null;
        if (!is_string($path) || !is_array($files)) {
            $errors[] = 'The installed Studio corpus manifest has a malformed group.';
            continue;
        }
        foreach ($files as $entry) {
            $file = is_array($entry) ? ($entry['file'] ?? null) : null;
            $relative = is_string($file) ? $path . '/' . $file : '';
            if ($relative === '' || isset($corpusFiles[$relative])) {
                $errors[] = 'The installed Studio corpus repeats or malforms a file path.';
                continue;
            }
            $corpusFiles[$relative] = true;
            try {
                \Kumwe\Producer\Schema\StudioContractResources::testkitBytes($relative);
            } catch (Throwable $error) {
                $errors[] = $relative . ' failed its released digest: ' . $error->getMessage();
            }
        }
    }
    if (count($corpusFiles) !== 301) {
        $errors[] = 'The package must contain exactly 301 released Studio corpus files.';
    }

    try {
        $release = \Kumwe\Producer\Schema\StudioContractResources::releaseRecord();
        if (
            $release->release() !== '0.1.0-beta.3'
            || $release->sourceCommit() !== '42b149251a9f17a2ef8f32db0d9dd1ac2fcfec8a'
        ) {
            $errors[] = 'The installed Studio beta.3 release coordinate or provenance commit drifted.';
        }
        $browser = \Kumwe\Producer\Schema\StudioContractResources::browserAsset('browser-module');
        $enhancement = \Kumwe\Producer\Schema\StudioContractResources::browserAsset('enhancement-runtime');
        if (
            strlen(\Kumwe\Producer\Schema\StudioContractResources::browserAssetBytes('browser-module'))
                !== $browser->bytes()
            || strlen(\Kumwe\Producer\Schema\StudioContractResources::browserAssetBytes('enhancement-runtime'))
                !== $enhancement->bytes()
        ) {
            $errors[] = 'The installed Studio browser assets failed their exact manifest proof.';
        }
        $fixture = \Kumwe\Producer\Schema\StudioContractResources::testkitBytes(
            'fixtures/blueprint.product.example.json'
        );
        $document = \Kumwe\Producer\Canonical\CanonicalJson::decode($fixture);
        $validation = \Kumwe\Producer\Schema\StudioDocumentSchemaRegistry::fromVendoredCorpus()
            ->validate('blueprint', $document);
        if (!$validation->valid() || $validation->diagnostics() !== []) {
            $errors[] = 'The package could not validate its exact Studio blueprint fixture.';
        }
    } catch (Throwable $error) {
        $errors[] = 'The installed Studio contract failed closed: ' . $error->getMessage();
    }

    if ($errors !== []) {
        fwrite(STDERR, "Package smoke failed:\n - " . implode("\n - ", array_unique($errors)) . "\n");
        exit(1);
    }

    $mode = $noDev ? 'authoritative no-dev' : 'development';
    echo "Package smoke verified ({$mode}): 70 public types, 55 Studio schemas, 301 corpus files.\n";
})();
