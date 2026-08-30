<?php

/**
 * Prove that Producer vendors one exact provenance-backed Studio release.
 *
 * The release record is byte-identical at the package, protocol, and
 * testkit boundaries. Studio's schema, corpus, and browser manifests define
 * every admitted byte. PIN.json binds those manifests, the eight public npm
 * envelopes, their provenance-authenticated source commit, both executable
 * browser assets, the complete redistribution notice/license closure, and
 * the explicit outer-archive release decision.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

$root = dirname(__DIR__) . '/resources/studio-contract';
$errors = [];

/**
 * Decode one required JSON object without accepting an ambiguous shape.
 *
 * @return array<string, mixed>
 */
function objectDocument(string $path, array &$errors): array
{
    if (!is_file($path)) {
        $errors[] = 'Required contract file is missing: ' . $path;

        return [];
    }

    try {
        $document = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        $errors[] = $path . ' is not valid JSON: ' . $exception->getMessage();

        return [];
    }

    if (!is_array($document) || array_is_list($document)) {
        $errors[] = $path . ' must contain a JSON object.';

        return [];
    }

    return $document;
}

/** Return the Studio SRI spelling of a file's SHA-256 digest. */
function sri(string $path): string
{
    return 'sha256-' . base64_encode((string) hash_file('sha256', $path, true));
}

/** Reject paths that could escape or ambiguously address the vendored tree. */
function safeRelative(string $path): bool
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

/**
 * Enumerate regular files beneath a root using normalized relative paths.
 *
 * @return array<string, true>
 */
function filesUnder(string $root, array &$errors): array
{
    $files = [];
    if (!is_dir($root)) {
        $errors[] = 'Required contract directory is missing: ' . $root;

        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isLink()) {
            $errors[] = 'Symbolic link in the vendored contract: ' . $file->getPathname();
            continue;
        }
        if (!$file->isFile()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $files[$relative] = true;
    }
    ksort($files);

    return $files;
}

$pinPath = $root . '/PIN.json';
$releasePath = $root . '/studio-release.json';
$protocolReleasePath = $root . '/protocol/studio-release.json';
$testkitReleasePath = $root . '/testkit/studio-release.json';
$schemaManifestPath = $root . '/protocol/schemas/manifest.json';
$corpusManifestPath = $root . '/testkit/corpus-manifest.json';
$browserManifestPath = $root . '/browser/studio-assets.json';

$pin = objectDocument($pinPath, $errors);
$release = objectDocument($releasePath, $errors);
$schemaManifest = objectDocument($schemaManifestPath, $errors);
$corpusManifest = objectDocument($corpusManifestPath, $errors);
$browserManifest = objectDocument($browserManifestPath, $errors);
$schemaSeen = [];
$corpusSeen = [];

$expectedFiles = [
    'PIN.json' => true,
    'studio-release.json' => true,
    'protocol/studio-release.json' => true,
    'protocol/schemas/manifest.json' => true,
    'testkit/studio-release.json' => true,
    'testkit/corpus-manifest.json' => true,
    'browser/studio-assets.json' => true,
];

$releaseBytes = is_file($releasePath) ? (string) file_get_contents($releasePath) : '';
foreach ([$protocolReleasePath, $testkitReleasePath] as $copy) {
    if (!is_file($copy) || !hash_equals($releaseBytes, (string) file_get_contents($copy))) {
        $errors[] = 'Studio release records are not byte-identical: ' . $copy;
    }
}

$releaseDigest = hash('sha256', $releaseBytes);
$releasePin = is_array($pin['release_record'] ?? null) ? $pin['release_record'] : [];
$source = is_array($pin['source'] ?? null) ? $pin['source'] : [];
if (
    ($source['repository'] ?? null) !== 'https://github.com/kumwe/studio'
    || ($source['kind'] ?? null) !== 'provenance-backed-npm-release'
    || ($source['release'] ?? null) !== '0.1.0-beta.2'
    || ($source['release'] ?? null) !== ($release['release'] ?? null)
    || ($source['commit'] ?? null) !== '38a96472ff4a5e1aa1fb92ed5451dc0fd112cf48'
    || ($source['workflow'] ?? null)
        !== 'https://github.com/kumwe/studio/actions/runs/33181863797/attempts/1'
) {
    $errors[] = 'PIN.json does not name the exact provenance-backed Studio beta.2 publication.';
}
if (
    ($releasePin['release'] ?? null) !== ($release['release'] ?? null)
    || ($releasePin['file'] ?? null) !== 'studio-release.json'
    || ($releasePin['sha256'] ?? null) !== $releaseDigest
) {
    $errors[] = 'PIN.json release_record does not bind the vendored release bytes.';
}
if (($pin['protocol_version'] ?? null) !== ($release['protocolVersion'] ?? null)) {
    $errors[] = 'PIN.json protocol_version differs from studio-release.json.';
}
if (($pin['corpus_manifest_digest'] ?? null) !== ($release['corpusManifestDigest'] ?? null)) {
    $errors[] = 'PIN.json corpus_manifest_digest differs from studio-release.json.';
}
if (($pin['claimed_profiles'] ?? null) !== ($release['claimedProfiles'] ?? null)) {
    $errors[] = 'PIN.json claimed_profiles differ from studio-release.json.';
}
if (($pin['packages'] ?? null) !== ($release['packages'] ?? null)) {
    $errors[] = 'PIN.json packages differ from studio-release.json.';
}

$packageNames = [
    '@kumwe/studio',
    '@kumwe/studio-core',
    '@kumwe/studio-media',
    '@kumwe/studio-preview',
    '@kumwe/studio-protocol',
    '@kumwe/studio-renderer-web',
    '@kumwe/studio-rich-text',
    '@kumwe/studio-testkit',
];
$packageMap = is_array($release['packages'] ?? null) ? $release['packages'] : [];
$actualPackageNames = array_keys($packageMap);
sort($actualPackageNames, SORT_STRING);
if ($actualPackageNames !== $packageNames) {
    $errors[] = 'The Studio release must name exactly its eight-package npm family.';
}
foreach ($packageMap as $name => $version) {
    if (!is_string($name) || $version !== ($release['release'] ?? null)) {
        $errors[] = 'Every Studio package must use the exact coordinated beta.2 version.';
    }
}

$provenanceEntries = is_array($pin['package_provenance'] ?? null)
    ? $pin['package_provenance']
    : [];
$provenanceSeen = [];
foreach ($provenanceEntries as $entry) {
    $name = is_array($entry) ? ($entry['name'] ?? null) : null;
    if (
        !is_string($name)
        || !isset($packageMap[$name])
        || isset($provenanceSeen[$name])
        || ($entry['version'] ?? null) !== $packageMap[$name]
        || !is_string($entry['tarball'] ?? null)
        || !str_starts_with($entry['tarball'], 'https://registry.npmjs.org/')
        || !is_int($entry['bytes'] ?? null)
        || $entry['bytes'] < 1
        || preg_match('/^[a-f0-9]{64}$/', (string) ($entry['sha256'] ?? '')) !== 1
        || preg_match('/^[a-f0-9]{40}$/', (string) ($entry['shasum'] ?? '')) !== 1
        || preg_match('/^sha512-[A-Za-z0-9+\/]+={0,2}$/', (string) ($entry['integrity'] ?? '')) !== 1
        || !is_string($entry['attestation'] ?? null)
        || !str_starts_with(
            $entry['attestation'],
            'https://registry.npmjs.org/-/npm/v1/attestations/',
        )
    ) {
        $errors[] = 'PIN.json carries a malformed or repeated npm provenance envelope.';
        continue;
    }
    $provenanceSeen[$name] = true;
}
$actualProvenanceNames = array_keys($provenanceSeen);
sort($actualProvenanceNames, SORT_STRING);
if ($actualProvenanceNames !== $packageNames) {
    $errors[] = 'PIN.json must bind one registry envelope for every Studio package.';
}

$browserPin = is_array($pin['browser_artifacts'] ?? null) ? $pin['browser_artifacts'] : [];
$manifestPin = is_array($browserPin['manifest'] ?? null) ? $browserPin['manifest'] : [];
$archivePin = is_array($browserPin['authoring_archive'] ?? null)
    ? $browserPin['authoring_archive']
    : [];
$resolvedAssets = is_array($browserPin['resolved_assets'] ?? null)
    ? $browserPin['resolved_assets']
    : [];
$redistributionFiles = is_array($browserPin['redistribution_files'] ?? null)
    ? $browserPin['redistribution_files']
    : [];
$browserLocators = is_array($release['browserArtifacts'] ?? null)
    ? $release['browserArtifacts']
    : [];
$manifestLocator = is_array($browserLocators['manifest'] ?? null)
    ? $browserLocators['manifest']
    : [];
$archiveLocator = is_array($browserLocators['authoringArchive'] ?? null)
    ? $browserLocators['authoringArchive']
    : [];
$enhancementLocator = is_array($browserLocators['enhancementRuntime'] ?? null)
    ? $browserLocators['enhancementRuntime']
    : [];
$browserPinMembers = array_keys($browserPin);
sort($browserPinMembers, SORT_STRING);
if (
    $browserPinMembers !== [
        'authoring_archive',
        'manifest',
        'redistribution_files',
        'resolved_assets',
    ]
    || count($redistributionFiles) !== 14
    || ($manifestLocator['name'] ?? null) !== 'studio-assets.json'
    || ($manifestLocator['schema'] ?? null)
        !== 'https://schemas.kumwe.org/studio/v1/studio-browser-assets.schema.json'
    || ($archiveLocator['archiveStem'] ?? null) !== 'studio-browser-' . ($release['release'] ?? '')
    || ($archiveLocator['assetRole'] ?? null) !== 'browser-module'
    || ($archiveLocator['loading'] ?? null) !== 'module'
    || ($enhancementLocator['assetRole'] ?? null) !== 'enhancement-runtime'
    || ($enhancementLocator['loading'] ?? null) !== 'defer'
    || ($enhancementLocator['package'] ?? null) !== '@kumwe/studio-renderer-web'
    || ($enhancementLocator['packageBasePath'] ?? null) !== 'dist/browser/'
    || ($manifestPin['file'] ?? null) !== 'browser/studio-assets.json'
    || ($manifestPin['package'] ?? null) !== '@kumwe/studio'
    || ($manifestPin['package_path'] ?? null) !== 'dist/browser/studio-assets.json'
    || ($manifestPin['sha256'] ?? null) !== hash_file('sha256', $browserManifestPath)
) {
    $errors[] = 'The Studio release and PIN do not bind the exact browser manifest locators.';
}

$readiness = is_array($pin['release_readiness'] ?? null) ? $pin['release_readiness'] : [];
$blockers = is_array($readiness['blockers'] ?? null) ? $readiness['blockers'] : [];
$archiveReason = $archivePin['reason'] ?? null;
if (
    ($readiness['status'] ?? null) !== 'blocked'
    || count($blockers) !== 1
    || !is_string($archiveReason)
    || $blockers !== [$archiveReason]
    || ($archivePin['archive_stem'] ?? null) !== ($archiveLocator['archiveStem'] ?? null)
    || ($archivePin['status'] ?? null) !== 'unavailable'
) {
    $errors[] = 'PIN.json must fail closed on the unavailable governed outer browser archive.';
}

$manifestAssets = is_array($browserManifest['assets'] ?? null) ? $browserManifest['assets'] : [];
$manifestAssetsByRole = [];
foreach ($manifestAssets as $entry) {
    $role = is_array($entry) ? ($entry['role'] ?? null) : null;
    if (in_array($role, ['browser-module', 'enhancement-runtime'], true)) {
        if (isset($manifestAssetsByRole[$role])) {
            $errors[] = 'studio-assets.json repeats an executable runtime role.';
            continue;
        }
        $manifestAssetsByRole[$role] = $entry;
    }
}
$resolvedAssetsByRole = [];
foreach ($resolvedAssets as $entry) {
    $role = is_array($entry) ? ($entry['role'] ?? null) : null;
    if (!is_string($role) || isset($resolvedAssetsByRole[$role])) {
        $errors[] = 'PIN.json repeats or malforms a resolved browser role.';
        continue;
    }
    $resolvedAssetsByRole[$role] = $entry;
}
if (
    ($browserManifest['kind'] ?? null) !== 'studio-browser-assets'
    || ($browserManifest['schemaVersion'] ?? null) !== 1
    || ($browserManifest['release']['version'] ?? null) !== ($release['release'] ?? null)
    || ($browserManifest['release']['corpusManifestDigest'] ?? null)
        !== ($release['corpusManifestDigest'] ?? null)
    || array_keys($manifestAssetsByRole) !== ['browser-module', 'enhancement-runtime']
    || array_keys($resolvedAssetsByRole) !== ['browser-module', 'enhancement-runtime']
    || ($browserManifest['module']['entryPoint'] ?? null)
        !== ($manifestAssetsByRole['browser-module']['path'] ?? null)
    || ($browserManifest['enhancementRuntime']['entryPoint'] ?? null)
        !== ($manifestAssetsByRole['enhancement-runtime']['path'] ?? null)
) {
    $errors[] = 'studio-assets.json does not identify the exact coordinated browser generation.';
}

$browserDirectFiles = ['browser/studio-assets.json'];
foreach (
    [
        'browser-module' => '@kumwe/studio',
        'enhancement-runtime' => '@kumwe/studio-renderer-web',
    ] as $role => $package
) {
    $asset = $manifestAssetsByRole[$role] ?? [];
    $binding = $resolvedAssetsByRole[$role] ?? [];
    $assetPath = is_string($asset['path'] ?? null) ? $asset['path'] : '';
    $relative = 'browser/' . $assetPath;
    $path = $root . '/' . $relative;
    $bytes = is_file($path) ? (string) file_get_contents($path) : '';
    $integrity = 'sha256-' . base64_encode(hash('sha256', $bytes, true));
    if (
        !safeRelative($assetPath)
        || ($asset['mediaType'] ?? null) !== 'text/javascript'
        || ($asset['minified'] ?? null) !== true
        || !is_int($asset['bytes'] ?? null)
        || !is_int($asset['budgetBytes'] ?? null)
        || $asset['bytes'] !== strlen($bytes)
        || $asset['bytes'] > $asset['budgetBytes']
        || ($asset['contentHash'] ?? null) !== hash('sha256', $bytes)
        || ($asset['integrity'] ?? null) !== $integrity
        || ($binding['file'] ?? null) !== $relative
        || ($binding['package'] ?? null) !== $package
        || ($binding['package_path'] ?? null) !== 'dist/browser/' . $assetPath
        || ($binding['bytes'] ?? null) !== ($asset['bytes'] ?? null)
        || ($binding['budget_bytes'] ?? null) !== ($asset['budgetBytes'] ?? null)
        || ($binding['content_hash'] ?? null) !== ($asset['contentHash'] ?? null)
        || ($binding['integrity'] ?? null) !== ($asset['integrity'] ?? null)
        || ($binding['minified'] ?? null) !== true
    ) {
        $errors[] = 'The resolved Studio browser asset is missing or disagrees: ' . $role;
        continue;
    }
    $browserDirectFiles[] = $relative;
    $expectedFiles[$relative] = true;
}

$manifestRedistribution = [];
$redistributionPaths = [];
$redistributionRoleCounts = ['license' => 0, 'notice' => 0];
foreach ($manifestAssets as $entry) {
    $role = is_array($entry) ? ($entry['role'] ?? null) : null;
    if (!in_array($role, ['license', 'notice'], true)) {
        continue;
    }
    $assetPath = is_array($entry) ? ($entry['path'] ?? null) : null;
    if (!is_string($assetPath) || isset($redistributionPaths[$assetPath])) {
        $errors[] = 'studio-assets.json repeats or malforms a redistribution path.';
        continue;
    }
    $redistributionPaths[$assetPath] = true;
    $redistributionRoleCounts[$role]++;
    $manifestRedistribution[] = $entry;
}
if (
    count($manifestRedistribution) !== 14
    || $redistributionRoleCounts !== ['license' => 13, 'notice' => 1]
    || count($redistributionFiles) !== count($manifestRedistribution)
) {
    $errors[] = 'Studio beta.2 must carry its complete 14-file redistribution closure.';
}
foreach ($manifestRedistribution as $index => $asset) {
    $binding = is_array($redistributionFiles[$index] ?? null)
        ? $redistributionFiles[$index]
        : [];
    $assetMembers = array_keys($asset);
    sort($assetMembers, SORT_STRING);
    $bindingMembers = array_keys($binding);
    sort($bindingMembers, SORT_STRING);
    $role = $asset['role'] ?? null;
    $assetPath = $asset['path'] ?? null;
    $relative = is_string($assetPath) ? 'browser/' . $assetPath : 'browser/invalid';
    $path = $root . '/' . $relative;
    $bytes = is_file($path) ? (string) file_get_contents($path) : '';
    $sha256 = hash('sha256', $bytes);
    $integrity = 'sha256-' . base64_encode(hash('sha256', $bytes, true));
    if (
        $assetMembers !== ['bytes', 'integrity', 'mediaType', 'path', 'role']
        || $bindingMembers !== [
            'bytes',
            'file',
            'integrity',
            'media_type',
            'package',
            'package_path',
            'role',
            'sha256',
        ]
        || !is_string($assetPath)
        || !safeRelative($assetPath)
        || !is_int($asset['bytes'] ?? null)
        || $asset['bytes'] < 1
        || ($asset['bytes'] ?? null) !== strlen($bytes)
        || ($asset['integrity'] ?? null) !== $integrity
        || ($role === 'notice'
            && ($assetPath !== 'THIRD_PARTY_NOTICES.md'
                || ($asset['mediaType'] ?? null) !== 'text/markdown'))
        || ($role === 'license' && ($asset['mediaType'] ?? null) !== 'text/plain')
        || ($role === 'license'
            && $assetPath !== 'LICENSE'
            && preg_match('#^third-party-licenses/[^/]+\.txt$#', $assetPath) !== 1)
        || ($binding['role'] ?? null) !== $role
        || ($binding['file'] ?? null) !== $relative
        || ($binding['package'] ?? null) !== '@kumwe/studio'
        || ($binding['package_path'] ?? null) !== 'dist/browser/' . $assetPath
        || ($binding['bytes'] ?? null) !== ($asset['bytes'] ?? null)
        || ($binding['media_type'] ?? null) !== ($asset['mediaType'] ?? null)
        || ($binding['integrity'] ?? null) !== ($asset['integrity'] ?? null)
        || ($binding['sha256'] ?? null) !== $sha256
    ) {
        $errors[] = 'The Studio redistribution file is missing or disagrees: ' . $relative;
        continue;
    }
    $browserDirectFiles[] = $relative;
    $expectedFiles[$relative] = true;
}

$directFiles = is_array($pin['files'] ?? null) ? $pin['files'] : [];
$directSeen = [];
foreach ($directFiles as $entry) {
    if (
        !is_array($entry)
        || !is_string($entry['file'] ?? null)
        || !is_string($entry['sha256'] ?? null)
        || !safeRelative($entry['file'])
    ) {
        $errors[] = 'PIN.json carries a malformed direct file entry.';
        continue;
    }
    $relative = $entry['file'];
    if (isset($directSeen[$relative])) {
        $errors[] = 'PIN.json repeats a direct file: ' . $relative;
        continue;
    }
    $directSeen[$relative] = true;
    $path = $root . '/' . $relative;
    if (!is_file($path) || !hash_equals($entry['sha256'], (string) hash_file('sha256', $path))) {
        $errors[] = 'Pinned direct-file digest mismatch: ' . $relative;
    }
}
$requiredDirect = [
    'studio-release.json',
    'protocol/studio-release.json',
    'protocol/schemas/manifest.json',
    'testkit/studio-release.json',
    'testkit/corpus-manifest.json',
    ...$browserDirectFiles,
];
sort($requiredDirect);
$actualDirect = array_keys($directSeen);
sort($actualDirect);
if ($actualDirect !== $requiredDirect) {
    $errors[] = 'PIN.json must bind exactly the release records, manifests, executable browser assets, '
        . 'and redistribution evidence.';
}

if (
    ($schemaManifest['kind'] ?? null) !== 'schema-manifest'
    || !is_array($schemaManifest['schemas'] ?? null)
) {
    $errors[] = 'The protocol schema manifest is malformed.';
} else {
    foreach ($schemaManifest['schemas'] as $entry) {
        if (
            !is_array($entry)
            || !is_string($entry['file'] ?? null)
            || !is_string($entry['digest'] ?? null)
            || !safeRelative($entry['file'])
        ) {
            $errors[] = 'The protocol schema manifest carries a malformed entry.';
            continue;
        }
        $relative = 'protocol/schemas/' . $entry['file'];
        if (isset($schemaSeen[$relative])) {
            $errors[] = 'The protocol schema manifest repeats: ' . $entry['file'];
            continue;
        }
        $schemaSeen[$relative] = true;
        $expectedFiles[$relative] = true;
        $path = $root . '/' . $relative;
        if (!is_file($path) || !hash_equals($entry['digest'], sri($path))) {
            $errors[] = 'Released protocol schema digest mismatch: ' . $entry['file'];
        }
    }
}
if (count($schemaSeen) !== 55) {
    $errors[] = 'The Studio beta.2 schema manifest must name exactly 55 files.';
}

if (
    ($corpusManifest['contractVersion'] ?? null) !== ($release['contractVersion'] ?? null)
    || !is_array($corpusManifest['groups'] ?? null)
) {
    $errors[] = 'The Studio testkit corpus manifest is malformed.';
} else {
    foreach ($corpusManifest['groups'] as $group) {
        if (
            !is_array($group)
            || !is_string($group['path'] ?? null)
            || !safeRelative($group['path'])
            || !is_array($group['files'] ?? null)
        ) {
            $errors[] = 'The Studio corpus manifest carries a malformed group.';
            continue;
        }
        foreach ($group['files'] as $entry) {
            if (
                !is_array($entry)
                || !is_string($entry['file'] ?? null)
                || !is_string($entry['digest'] ?? null)
                || !safeRelative($entry['file'])
            ) {
                $errors[] = 'The Studio corpus manifest carries a malformed file entry.';
                continue;
            }
            $relative = 'testkit/' . $group['path'] . '/' . $entry['file'];
            if (isset($corpusSeen[$relative])) {
                $errors[] = 'The Studio corpus manifest repeats: ' . $relative;
                continue;
            }
            $corpusSeen[$relative] = true;
            $expectedFiles[$relative] = true;
            $path = $root . '/' . $relative;
            if (!is_file($path) || !hash_equals($entry['digest'], sri($path))) {
                $errors[] = 'Released Studio corpus digest mismatch: ' . $relative;
            }
        }
    }
}
if (count($corpusSeen) !== 301) {
    $errors[] = 'The Studio beta.2 corpus manifest must name exactly 301 files.';
}

$corpusDigest = is_file($corpusManifestPath) ? sri($corpusManifestPath) : '';
if (($release['corpusManifestDigest'] ?? null) !== $corpusDigest) {
    $errors[] = 'studio-release.json does not bind the vendored corpus manifest bytes.';
}

$presentFiles = filesUnder($root, $errors);
foreach (array_diff_key($expectedFiles, $presentFiles) as $relative => $_) {
    $errors[] = 'Manifested contract file is missing: ' . $relative;
}
foreach (array_diff_key($presentFiles, $expectedFiles) as $relative => $_) {
    $errors[] = 'Unmanifested file in the vendored contract: ' . $relative;
}

if ($errors !== []) {
    fwrite(STDERR, "Studio contract verification failed:\n - " . implode("\n - ", array_unique($errors)) . "\n");
    exit(1);
}

echo sprintf(
    "Studio contract verified: release %s, protocol %s, %d schemas, %d corpus files, "
        . "%d redistribution files.\n",
    $release['release'],
    $release['protocolVersion'],
    count($schemaManifest['schemas']),
    count($corpusSeen),
    count($manifestRedistribution),
);
