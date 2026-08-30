<?php

/**
 * Import one immutable Studio generation from manifests and published npm bits.
 *
 * Usage:
 * php tools/import-studio-contract.php STUDIO_ROOT EVIDENCE_JSON STUDIO_TGZ RENDERER_TGZ
 *
 * The Studio checkout supplies only source-controlled release, schema and
 * testkit bytes. Browser bytes come from the two provenance-backed npm
 * tarballs named by the release record. The importer verifies every source
 * manifest entry, both tarball envelopes, the asset manifest, the two
 * selected browser roles, and the complete manifest-declared redistribution
 * notice/license closure before replacing resources/studio-contract.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

const PRODUCER_IMPORT_ROOT = __DIR__ . '/../resources/studio-contract';
const PRODUCER_IMPORT_MAX_BYTES = 16777216;

try {
    exit(producerImportMain($_SERVER['argv'] ?? []));
} catch (Throwable $error) {
    fwrite(STDERR, "Studio contract import failed: {$error->getMessage()}\n");
    exit(1);
}

/**
 * Import and atomically replace the exact contract generation.
 *
 * @param list<string> $arguments Command plus four required paths.
 *
 * @since 0.2.0
 */
function producerImportMain(array $arguments): int
{
    if (count($arguments) !== 5) {
        fwrite(
            STDERR,
            "Usage: php tools/import-studio-contract.php "
                . "STUDIO_ROOT EVIDENCE_JSON STUDIO_TGZ RENDERER_TGZ\n",
        );

        return 2;
    }

    [, $studioArgument, $evidenceArgument, $studioArchiveArgument, $rendererArchiveArgument] = $arguments;
    $studioRoot = producerImportDirectory($studioArgument, 'Studio source root');
    $evidencePath = producerImportFile($evidenceArgument, 'publication evidence');
    $studioArchive = producerImportFile($studioArchiveArgument, '@kumwe/studio archive');
    $rendererArchive = producerImportFile($rendererArchiveArgument, 'renderer-web archive');
    $evidence = producerImportObject(producerImportBytes($evidencePath), $evidencePath);

    $source = $evidence->source ?? null;
    $sourceCommit = $source instanceof stdClass ? ($source->commit ?? null) : null;
    if (
        ($evidence->kind ?? null) !== 'studio-npm-publication-evidence'
        || !is_string($sourceCommit)
        || preg_match('/^[a-f0-9]{40}$/', $sourceCommit) !== 1
        || ($source->repository ?? null) !== 'https://github.com/kumwe/studio'
        || !is_string($source->workflow ?? null)
    ) {
        throw new RuntimeException('Publication evidence has no exact Studio source identity.');
    }
    if (!hash_equals($sourceCommit, producerImportGitCommit($studioRoot))) {
        throw new RuntimeException('Studio source checkout differs from the publication commit.');
    }

    $releaseBytes = producerImportBytes($studioRoot . '/studio-release.json');
    $protocolReleaseBytes = producerImportBytes($studioRoot . '/packages/protocol/studio-release.json');
    $testkitReleaseBytes = producerImportBytes($studioRoot . '/packages/testkit/studio-release.json');
    if (
        !hash_equals($releaseBytes, $protocolReleaseBytes)
        || !hash_equals($releaseBytes, $testkitReleaseBytes)
    ) {
        throw new RuntimeException('Studio release-record copies are not byte-identical.');
    }
    $release = producerImportObject($releaseBytes, $studioRoot . '/studio-release.json');
    $releaseVersion = $release->release ?? null;
    if (
        ($release->kind ?? null) !== 'studio-release'
        || !is_string($releaseVersion)
        || ($evidence->release ?? null) !== $releaseVersion
        || !($release->packages ?? null) instanceof stdClass
        || !is_array($release->claimedProfiles ?? null)
    ) {
        throw new RuntimeException('Studio release record and publication evidence disagree.');
    }

    $packageEvidence = producerImportPackageEvidence($evidence, $release);
    producerImportVerifyArchive($studioArchive, $packageEvidence['@kumwe/studio']);
    producerImportVerifyArchive($rendererArchive, $packageEvidence['@kumwe/studio-renderer-web']);
    $studioPhar = new PharData($studioArchive);
    $rendererPhar = new PharData($rendererArchive);

    $assetManifestBytes = producerImportPharBytes(
        $studioPhar,
        'package/dist/browser/studio-assets.json',
        '@kumwe/studio',
    );
    $assetManifest = producerImportObject($assetManifestBytes, '@kumwe/studio:studio-assets.json');
    [$browserAsset, $enhancementAsset, $redistributionAssets] = producerImportBrowserManifest(
        $release,
        $assetManifest,
    );

    $browserBytes = producerImportPharBytes(
        $studioPhar,
        'package/dist/browser/' . $browserAsset->path,
        '@kumwe/studio',
    );
    $enhancementBytes = producerImportPharBytes(
        $rendererPhar,
        'package/dist/browser/' . $enhancementAsset->path,
        '@kumwe/studio-renderer-web',
    );
    $studioEnhancementBytes = producerImportPharBytes(
        $studioPhar,
        'package/dist/browser/' . $enhancementAsset->path,
        '@kumwe/studio',
    );
    if (!hash_equals($enhancementBytes, $studioEnhancementBytes)) {
        throw new RuntimeException('Studio and renderer-web carry different enhancement-runtime bytes.');
    }
    producerImportVerifyAsset($browserAsset, $browserBytes);
    producerImportVerifyAsset($enhancementAsset, $enhancementBytes);

    $redistributionBytes = [];
    foreach ($redistributionAssets as $asset) {
        $path = (string) $asset->path;
        $bytes = producerImportPharBytes(
            $studioPhar,
            'package/dist/browser/' . $path,
            '@kumwe/studio',
        );
        producerImportVerifyRedistributionAsset($asset, $bytes);
        $redistributionBytes[$path] = $bytes;
    }

    $schemaManifestPath = $studioRoot . '/packages/protocol/schemas/manifest.json';
    $corpusManifestPath = $studioRoot . '/packages/testkit/corpus-manifest.json';
    $schemaManifestBytes = producerImportBytes($schemaManifestPath);
    $corpusManifestBytes = producerImportBytes($corpusManifestPath);
    $schemaManifest = producerImportObject($schemaManifestBytes, $schemaManifestPath);
    $corpusManifest = producerImportObject($corpusManifestBytes, $corpusManifestPath);

    $expected = [
        'studio-release.json' => $releaseBytes,
        'protocol/studio-release.json' => $protocolReleaseBytes,
        'protocol/schemas/manifest.json' => $schemaManifestBytes,
        'testkit/studio-release.json' => $testkitReleaseBytes,
        'testkit/corpus-manifest.json' => $corpusManifestBytes,
        'browser/studio-assets.json' => $assetManifestBytes,
        'browser/' . $browserAsset->path => $browserBytes,
        'browser/' . $enhancementAsset->path => $enhancementBytes,
    ];
    foreach ($redistributionBytes as $path => $bytes) {
        $expected['browser/' . $path] = $bytes;
    }
    $schemaCount = producerImportSchemas($studioRoot, $schemaManifest, $expected);
    $corpusCount = producerImportCorpus($studioRoot, $corpusManifest, $expected);

    $corpusDigest = producerImportSri($corpusManifestBytes);
    if (!hash_equals((string) ($release->corpusManifestDigest ?? ''), $corpusDigest)) {
        throw new RuntimeException('Studio release record does not bind its corpus manifest bytes.');
    }

    $pin = producerImportPin(
        $evidence,
        $release,
        $packageEvidence,
        $expected,
        $browserAsset,
        $enhancementAsset,
        $redistributionAssets,
    );
    $expected['PIN.json'] = producerImportJson($pin);
    ksort($expected, SORT_STRING);
    producerImportReplace($expected);

    fwrite(
        STDOUT,
        sprintf(
            "Imported Studio %s at %s: %d schemas, %d corpus files, "
                . "2 verified browser assets, %d redistribution files.\n",
            $releaseVersion,
            $sourceCommit,
            $schemaCount,
            $corpusCount,
            count($redistributionAssets),
        ),
    );

    return 0;
}

/** Resolve one ordinary directory to an absolute path. */
function producerImportDirectory(string $path, string $label): string
{
    $resolved = realpath($path);
    if ($resolved === false || !is_dir($resolved) || is_link($path)) {
        throw new RuntimeException($label . ' is missing, linked, or not a directory.');
    }

    return $resolved;
}

/** Resolve one ordinary file to an absolute path. */
function producerImportFile(string $path, string $label): string
{
    $resolved = realpath($path);
    if ($resolved === false || !is_file($resolved) || is_link($path)) {
        throw new RuntimeException($label . ' is missing, linked, or not a file.');
    }

    return $resolved;
}

/** Read one bounded source or archive-member file. */
function producerImportBytes(string $path): string
{
    $bytes = file_get_contents($path);
    if (!is_string($bytes) || strlen($bytes) > PRODUCER_IMPORT_MAX_BYTES) {
        throw new RuntimeException('Import input is unreadable or exceeds its byte bound: ' . $path);
    }

    return $bytes;
}

/** Decode one JSON object with a source-bearing error. */
function producerImportObject(string $bytes, string $source): stdClass
{
    try {
        $document = json_decode($bytes, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new RuntimeException($source . ' is not valid JSON.', 0, $error);
    }
    if (!$document instanceof stdClass) {
        throw new RuntimeException($source . ' must contain a JSON object.');
    }

    return $document;
}

/** Read the detached source coordinate without accepting an abbreviated SHA. */
function producerImportGitCommit(string $studioRoot): string
{
    $output = [];
    $status = 0;
    exec(
        'git -C ' . escapeshellarg($studioRoot) . ' rev-parse --verify HEAD 2>&1',
        $output,
        $status,
    );
    $commit = trim(implode("\n", $output));
    if ($status !== 0 || preg_match('/^[a-f0-9]{40}$/', $commit) !== 1) {
        throw new RuntimeException('Could not resolve the Studio source commit.');
    }

    return $commit;
}

/**
 * Validate the eight provenance-backed package records against the release.
 *
 * @return array<string, stdClass>
 */
function producerImportPackageEvidence(stdClass $evidence, stdClass $release): array
{
    $entries = $evidence->packages ?? null;
    if (!is_array($entries) || !array_is_list($entries)) {
        throw new RuntimeException('Publication evidence has no ordered package list.');
    }
    $versions = get_object_vars($release->packages);
    $packages = [];
    foreach ($entries as $entry) {
        if (!$entry instanceof stdClass) {
            throw new RuntimeException('Publication evidence carries a malformed package entry.');
        }
        $name = $entry->name ?? null;
        if (
            !is_string($name)
            || !isset($versions[$name])
            || isset($packages[$name])
            || ($entry->version ?? null) !== $versions[$name]
            || !is_string($entry->tarball ?? null)
            || !is_string($entry->attestation ?? null)
            || !is_int($entry->bytes ?? null)
            || $entry->bytes < 1
            || preg_match('/^[a-f0-9]{64}$/', (string) ($entry->sha256 ?? '')) !== 1
            || preg_match('/^[a-f0-9]{40}$/', (string) ($entry->shasum ?? '')) !== 1
            || preg_match('/^sha512-[A-Za-z0-9+\/]+={0,2}$/', (string) ($entry->integrity ?? '')) !== 1
        ) {
            throw new RuntimeException('Publication evidence carries an invalid package coordinate.');
        }
        $packages[$name] = $entry;
    }
    ksort($packages, SORT_STRING);
    ksort($versions, SORT_STRING);
    if (array_keys($packages) !== array_keys($versions) || count($packages) !== 8) {
        throw new RuntimeException('Publication evidence must bind exactly the coordinated eight-package family.');
    }

    return $packages;
}

/** Prove one downloaded npm tarball against its public registry record. */
function producerImportVerifyArchive(string $path, stdClass $evidence): void
{
    $bytes = producerImportBytes($path);
    $integrity = 'sha512-' . base64_encode(hash('sha512', $bytes, true));
    if (
        strlen($bytes) !== $evidence->bytes
        || !hash_equals($evidence->sha256, hash('sha256', $bytes))
        || !hash_equals($evidence->shasum, sha1($bytes))
        || !hash_equals($evidence->integrity, $integrity)
    ) {
        throw new RuntimeException($evidence->name . ' tarball differs from its publication evidence.');
    }
}

/** Read one exact regular member from a verified npm archive. */
function producerImportPharBytes(PharData $archive, string $path, string $package): string
{
    if (!isset($archive[$path]) || !$archive[$path]->isFile()) {
        throw new RuntimeException($package . ' is missing required archive member ' . $path . '.');
    }
    $bytes = $archive[$path]->getContent();
    if (strlen($bytes) > PRODUCER_IMPORT_MAX_BYTES) {
        throw new RuntimeException($package . ' archive member exceeds the import byte bound.');
    }

    return $bytes;
}

/**
 * Resolve exactly one browser module and enhancement runtime from the manifest.
 *
 * @return array{0: stdClass, 1: stdClass, 2: list<stdClass>}
 */
function producerImportBrowserManifest(stdClass $release, stdClass $manifest): array
{
    $locators = $release->browserArtifacts ?? null;
    $releaseIdentity = $manifest->release ?? null;
    if (
        !$locators instanceof stdClass
        || ($locators->manifest->name ?? null) !== 'studio-assets.json'
        || ($locators->manifest->schema ?? null)
            !== 'https://schemas.kumwe.org/studio/v1/studio-browser-assets.schema.json'
        || ($manifest->kind ?? null) !== 'studio-browser-assets'
        || ($manifest->schemaVersion ?? null) !== 1
        || !$releaseIdentity instanceof stdClass
        || ($releaseIdentity->version ?? null) !== ($release->release ?? null)
        || ($releaseIdentity->corpusManifestDigest ?? null) !== ($release->corpusManifestDigest ?? null)
        || !is_array($manifest->assets ?? null)
        || !array_is_list($manifest->assets)
    ) {
        throw new RuntimeException('Browser manifest does not identify the coordinated Studio release.');
    }

    $byRole = [];
    foreach ($manifest->assets as $asset) {
        $role = $asset instanceof stdClass ? ($asset->role ?? null) : null;
        if (!is_string($role)) {
            throw new RuntimeException('Browser manifest carries an asset without a role.');
        }
        $byRole[$role][] = $asset;
    }
    if (
        count($byRole['browser-module'] ?? []) !== 1
        || count($byRole['enhancement-runtime'] ?? []) !== 1
    ) {
        throw new RuntimeException('Browser manifest must resolve exactly one required asset for each runtime role.');
    }
    $browser = $byRole['browser-module'][0];
    $enhancement = $byRole['enhancement-runtime'][0];
    if (
        ($locators->authoringArchive->assetRole ?? null) !== 'browser-module'
        || ($locators->authoringArchive->loading ?? null) !== 'module'
        || ($locators->authoringArchive->archiveStem ?? null)
            !== 'studio-browser-' . $release->release
        || ($locators->enhancementRuntime->assetRole ?? null) !== 'enhancement-runtime'
        || ($locators->enhancementRuntime->loading ?? null) !== 'defer'
        || ($locators->enhancementRuntime->package ?? null) !== '@kumwe/studio-renderer-web'
        || ($locators->enhancementRuntime->packageBasePath ?? null) !== 'dist/browser/'
        || ($manifest->module->entryPoint ?? null) !== ($browser->path ?? null)
        || ($manifest->enhancementRuntime->entryPoint ?? null) !== ($enhancement->path ?? null)
    ) {
        throw new RuntimeException('Browser artifact locators differ from their manifest entries.');
    }

    $redistribution = [];
    $redistributionPaths = [];
    $roleCounts = ['license' => 0, 'notice' => 0];
    foreach ($manifest->assets as $asset) {
        $role = $asset instanceof stdClass ? ($asset->role ?? null) : null;
        if (!in_array($role, ['license', 'notice'], true)) {
            continue;
        }
        $path = $asset instanceof stdClass ? ($asset->path ?? null) : null;
        if (!is_string($path) || isset($redistributionPaths[$path])) {
            throw new RuntimeException('Browser manifest repeats or malforms a redistribution path.');
        }
        $redistributionPaths[$path] = true;
        $roleCounts[$role]++;
        $redistribution[] = $asset;
    }
    if (
        count($redistribution) !== 14
        || $roleCounts !== ['license' => 13, 'notice' => 1]
    ) {
        throw new RuntimeException(
            'Studio beta.2 browser manifest must declare its complete 14-file redistribution closure.',
        );
    }

    return [$browser, $enhancement, $redistribution];
}

/** Prove selected browser bytes against every authoritative manifest field. */
function producerImportVerifyAsset(stdClass $asset, string $bytes): void
{
    $sha256 = hash('sha256', $bytes);
    $integrity = 'sha256-' . base64_encode(hash('sha256', $bytes, true));
    if (
        !is_string($asset->path ?? null)
        || !is_string($asset->role ?? null)
        || !is_int($asset->bytes ?? null)
        || !is_int($asset->budgetBytes ?? null)
        || ($asset->minified ?? null) !== true
        || $asset->bytes !== strlen($bytes)
        || $asset->bytes > $asset->budgetBytes
        || !hash_equals((string) ($asset->contentHash ?? ''), $sha256)
        || !hash_equals((string) ($asset->integrity ?? ''), $integrity)
        || !str_contains($asset->path, substr($sha256, 0, 16))
    ) {
        throw new RuntimeException('Browser asset bytes differ from studio-assets.json.');
    }
}

/** Prove one manifest-declared notice/license member from @kumwe/studio. */
function producerImportVerifyRedistributionAsset(stdClass $asset, string $bytes): void
{
    $members = array_map('strval', array_keys(get_object_vars($asset)));
    sort($members, SORT_STRING);
    $role = $asset->role ?? null;
    $path = $asset->path ?? null;
    $mediaType = $asset->mediaType ?? null;
    $integrity = 'sha256-' . base64_encode(hash('sha256', $bytes, true));
    if (
        $members !== ['bytes', 'integrity', 'mediaType', 'path', 'role']
        || !in_array($role, ['license', 'notice'], true)
        || !is_string($path)
        || !producerImportSafeRelative($path)
        || !is_int($asset->bytes ?? null)
        || $asset->bytes < 1
        || $asset->bytes !== strlen($bytes)
        || !is_string($asset->integrity ?? null)
        || !hash_equals($asset->integrity, $integrity)
        || ($role === 'notice' && ($path !== 'THIRD_PARTY_NOTICES.md' || $mediaType !== 'text/markdown'))
        || (
            $role === 'license'
            && $mediaType !== 'text/plain'
        )
        || (
            $role === 'license'
            && $path !== 'LICENSE'
            && preg_match('#^third-party-licenses/[^/]+\.txt$#', $path) !== 1
        )
    ) {
        throw new RuntimeException('Redistribution bytes differ from studio-assets.json: ' . (string) $path);
    }
}

/**
 * Add every schema-manifest member to the output map.
 *
 * @param array<string, string> $expected
 */
function producerImportSchemas(string $studioRoot, stdClass $manifest, array &$expected): int
{
    if (($manifest->kind ?? null) !== 'schema-manifest' || !is_array($manifest->schemas ?? null)) {
        throw new RuntimeException('Studio schema manifest is malformed.');
    }
    $seen = [];
    foreach ($manifest->schemas as $entry) {
        $file = $entry instanceof stdClass ? ($entry->file ?? null) : null;
        $digest = $entry instanceof stdClass ? ($entry->digest ?? null) : null;
        if (!is_string($file) || !producerImportSafeRelative($file) || isset($seen[$file])) {
            throw new RuntimeException('Studio schema manifest carries a malformed or repeated path.');
        }
        $bytes = producerImportBytes($studioRoot . '/packages/protocol/schemas/' . $file);
        if (!is_string($digest) || !hash_equals($digest, producerImportSri($bytes))) {
            throw new RuntimeException('Studio schema digest mismatch: ' . $file);
        }
        $seen[$file] = true;
        $expected['protocol/schemas/' . $file] = $bytes;
    }

    return count($seen);
}

/**
 * Add every corpus-manifest member to the output map.
 *
 * @param array<string, string> $expected
 */
function producerImportCorpus(string $studioRoot, stdClass $manifest, array &$expected): int
{
    if (!is_array($manifest->groups ?? null) || !array_is_list($manifest->groups)) {
        throw new RuntimeException('Studio corpus manifest is malformed.');
    }
    $seen = [];
    foreach ($manifest->groups as $group) {
        $path = $group instanceof stdClass ? ($group->path ?? null) : null;
        $files = $group instanceof stdClass ? ($group->files ?? null) : null;
        if (!is_string($path) || !producerImportSafeRelative($path) || !is_array($files)) {
            throw new RuntimeException('Studio corpus manifest carries a malformed group.');
        }
        foreach ($files as $entry) {
            $file = $entry instanceof stdClass ? ($entry->file ?? null) : null;
            $digest = $entry instanceof stdClass ? ($entry->digest ?? null) : null;
            $relative = is_string($file) ? $path . '/' . $file : '';
            if (!is_string($file) || !producerImportSafeRelative($file) || isset($seen[$relative])) {
                throw new RuntimeException('Studio corpus manifest carries a malformed or repeated path.');
            }
            $bytes = producerImportBytes($studioRoot . '/packages/testkit/' . $relative);
            if (!is_string($digest) || !hash_equals($digest, producerImportSri($bytes))) {
                throw new RuntimeException('Studio corpus digest mismatch: ' . $relative);
            }
            $seen[$relative] = true;
            $expected['testkit/' . $relative] = $bytes;
        }
    }

    return count($seen);
}

/**
 * Construct the shipped provenance and direct-file pin.
 *
 * @param array<string, stdClass> $packageEvidence
 * @param array<string, string>   $expected
 * @param list<stdClass>          $redistributionAssets
 *
 * @return array<string, mixed>
 */
function producerImportPin(
    stdClass $evidence,
    stdClass $release,
    array $packageEvidence,
    array $expected,
    stdClass $browserAsset,
    stdClass $enhancementAsset,
    array $redistributionAssets,
): array {
    $directPaths = [
        'studio-release.json',
        'protocol/studio-release.json',
        'protocol/schemas/manifest.json',
        'testkit/studio-release.json',
        'testkit/corpus-manifest.json',
        'browser/studio-assets.json',
        'browser/' . $browserAsset->path,
        'browser/' . $enhancementAsset->path,
    ];
    foreach ($redistributionAssets as $asset) {
        $directPaths[] = 'browser/' . $asset->path;
    }
    $files = [];
    foreach ($directPaths as $path) {
        $files[] = ['file' => $path, 'sha256' => hash('sha256', $expected[$path])];
    }

    $packages = [];
    foreach ($packageEvidence as $entry) {
        $packages[] = [
            'name' => $entry->name,
            'version' => $entry->version,
            'tarball' => $entry->tarball,
            'bytes' => $entry->bytes,
            'sha256' => $entry->sha256,
            'shasum' => $entry->shasum,
            'integrity' => $entry->integrity,
            'attestation' => $entry->attestation,
        ];
    }

    $archive = $evidence->browserArchive ?? null;
    if (
        !$archive instanceof stdClass
        || ($archive->archiveStem ?? null) !== ($release->browserArtifacts->authoringArchive->archiveStem ?? null)
        || ($archive->status ?? null) !== 'unavailable'
        || !is_string($archive->reason ?? null)
    ) {
        throw new RuntimeException('Publication evidence must state the unavailable approved browser archive.');
    }

    return [
        'pin' => 'kumwe-producer-studio-contract',
        'source' => [
            'repository' => $evidence->source->repository,
            'kind' => 'provenance-backed-npm-release',
            'release' => $release->release,
            'commit' => $evidence->source->commit,
            'workflow' => $evidence->source->workflow,
        ],
        'release_record' => [
            'release' => $release->release,
            'file' => 'studio-release.json',
            'sha256' => hash('sha256', $expected['studio-release.json']),
        ],
        'protocol_version' => $release->protocolVersion,
        'corpus_manifest_digest' => $release->corpusManifestDigest,
        'claimed_profiles' => $release->claimedProfiles,
        'packages' => get_object_vars($release->packages),
        'package_provenance' => $packages,
        'browser_artifacts' => [
            'manifest' => [
                'file' => 'browser/studio-assets.json',
                'package' => '@kumwe/studio',
                'package_path' => 'dist/browser/studio-assets.json',
                'sha256' => hash('sha256', $expected['browser/studio-assets.json']),
            ],
            'authoring_archive' => [
                'archive_stem' => $archive->archiveStem,
                'status' => $archive->status,
                'reason' => $archive->reason,
            ],
            'resolved_assets' => [
                producerImportPinnedAsset($browserAsset, '@kumwe/studio'),
                producerImportPinnedAsset($enhancementAsset, '@kumwe/studio-renderer-web'),
            ],
            'redistribution_files' => array_map(
                static fn (stdClass $asset): array => producerImportPinnedRedistributionFile(
                    $asset,
                    $expected['browser/' . $asset->path],
                ),
                $redistributionAssets,
            ),
        ],
        'release_readiness' => [
            'status' => 'blocked',
            'blockers' => [$archive->reason],
        ],
        'files' => $files,
    ];
}

/** @return array<string, mixed> */
function producerImportPinnedRedistributionFile(stdClass $asset, string $bytes): array
{
    return [
        'role' => $asset->role,
        'file' => 'browser/' . $asset->path,
        'package' => '@kumwe/studio',
        'package_path' => 'dist/browser/' . $asset->path,
        'bytes' => $asset->bytes,
        'media_type' => $asset->mediaType,
        'integrity' => $asset->integrity,
        'sha256' => hash('sha256', $bytes),
    ];
}

/** @return array<string, mixed> */
function producerImportPinnedAsset(stdClass $asset, string $package): array
{
    return [
        'role' => $asset->role,
        'file' => 'browser/' . $asset->path,
        'package' => $package,
        'package_path' => 'dist/browser/' . $asset->path,
        'bytes' => $asset->bytes,
        'budget_bytes' => $asset->budgetBytes,
        'content_hash' => $asset->contentHash,
        'integrity' => $asset->integrity,
        'minified' => $asset->minified,
    ];
}

/** Encode one deterministic reviewed JSON document. */
function producerImportJson(array $document): string
{
    $bytes = json_encode(
        $document,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );

    return $bytes . "\n";
}

/** Atomically replace only the dedicated contract resource directory. */
function producerImportReplace(array $files): void
{
    $target = PRODUCER_IMPORT_ROOT;
    $parent = dirname($target);
    $temporary = $parent . '/.studio-contract-import-' . bin2hex(random_bytes(8));
    $backup = $parent . '/.studio-contract-backup-' . bin2hex(random_bytes(8));
    if (!mkdir($temporary, 0700, true)) {
        throw new RuntimeException('Could not create the isolated contract import directory.');
    }

    try {
        foreach ($files as $relative => $bytes) {
            if (!producerImportSafeRelative($relative)) {
                throw new RuntimeException('Importer produced an unsafe output path.');
            }
            $path = $temporary . '/' . $relative;
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
                throw new RuntimeException('Could not create an imported contract directory.');
            }
            if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) {
                throw new RuntimeException('Could not write complete imported contract bytes.');
            }
        }
        if (is_link($target) || !is_dir($target) || !rename($target, $backup)) {
            throw new RuntimeException('Could not isolate the prior contract generation.');
        }
        if (!rename($temporary, $target)) {
            rename($backup, $target);
            throw new RuntimeException('Could not install the imported contract generation.');
        }
        producerImportRemoveTree($backup, $parent);
    } catch (Throwable $error) {
        if (is_dir($temporary)) {
            producerImportRemoveTree($temporary, $parent);
        }
        throw $error;
    }
}

/** Remove only a generated sibling tree after checking its exact parent. */
function producerImportRemoveTree(string $root, string $requiredParent): void
{
    if (dirname($root) !== $requiredParent || !is_dir($root) || is_link($root)) {
        throw new RuntimeException('Refusing to remove a non-import tree.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        if ($entry->isLink() || $entry->isFile()) {
            if (!unlink($entry->getPathname())) {
                throw new RuntimeException('Could not remove a superseded import file.');
            }
        } elseif (!rmdir($entry->getPathname())) {
            throw new RuntimeException('Could not remove a superseded import directory.');
        }
    }
    if (!rmdir($root)) {
        throw new RuntimeException('Could not remove the superseded import root.');
    }
}

/** Reject traversal, empty segments, links-by-spelling, and platform ambiguity. */
function producerImportSafeRelative(string $path): bool
{
    if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\')) {
        return false;
    }
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return false;
        }
    }

    return true;
}

/** Return Studio's canonical SHA-256 SRI spelling for bytes. */
function producerImportSri(string $bytes): string
{
    return 'sha256-' . base64_encode(hash('sha256', $bytes, true));
}
