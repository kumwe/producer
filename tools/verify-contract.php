<?php

/**
 * Prove that Producer vendors one exact coordinated Studio release.
 *
 * The release record is byte-identical at the package, protocol, and
 * testkit boundaries. Studio's signed-off schema and corpus manifests
 * define every admitted file and digest; PIN.json binds those manifests
 * and the release metadata to the coordinate Kumwe App consumes.
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
    return $path !== ''
        && !str_starts_with($path, '/')
        && !str_contains($path, '\\')
        && !in_array('..', explode('/', $path), true);
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

$pin = objectDocument($pinPath, $errors);
$release = objectDocument($releasePath, $errors);
$schemaManifest = objectDocument($schemaManifestPath, $errors);
$corpusManifest = objectDocument($corpusManifestPath, $errors);
$schemaSeen = [];
$corpusSeen = [];

$expectedFiles = [
    'PIN.json' => true,
    'studio-release.json' => true,
    'protocol/studio-release.json' => true,
    'protocol/schemas/manifest.json' => true,
    'testkit/studio-release.json' => true,
    'testkit/corpus-manifest.json' => true,
];

$releaseBytes = is_file($releasePath) ? (string) file_get_contents($releasePath) : '';
foreach ([$protocolReleasePath, $testkitReleasePath] as $copy) {
    if (!is_file($copy) || !hash_equals($releaseBytes, (string) file_get_contents($copy))) {
        $errors[] = 'Studio release records are not byte-identical: ' . $copy;
    }
}

$releaseDigest = hash('sha256', $releaseBytes);
$releasePin = is_array($pin['release_record'] ?? null) ? $pin['release_record'] : [];
if (($pin['source']['repository'] ?? null) !== 'https://github.com/kumwe/studio'
    || ($pin['source']['kind'] ?? null) !== 'coordinated-release'
    || ($pin['source']['release'] ?? null) !== ($release['release'] ?? null)
) {
    $errors[] = 'PIN.json does not name the coordinated Studio release record.';
}
if (($releasePin['release'] ?? null) !== ($release['release'] ?? null)
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

$directFiles = is_array($pin['files'] ?? null) ? $pin['files'] : [];
$directSeen = [];
foreach ($directFiles as $entry) {
    if (!is_array($entry)
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
];
sort($requiredDirect);
$actualDirect = array_keys($directSeen);
sort($actualDirect);
if ($actualDirect !== $requiredDirect) {
    $errors[] = 'PIN.json must bind exactly the release records and both released manifests.';
}

if (($schemaManifest['kind'] ?? null) !== 'schema-manifest'
    || !is_array($schemaManifest['schemas'] ?? null)
) {
    $errors[] = 'The protocol schema manifest is malformed.';
} else {
    foreach ($schemaManifest['schemas'] as $entry) {
        if (!is_array($entry)
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

if (($corpusManifest['contractVersion'] ?? null) !== ($release['contractVersion'] ?? null)
    || !is_array($corpusManifest['groups'] ?? null)
) {
    $errors[] = 'The Studio testkit corpus manifest is malformed.';
} else {
    foreach ($corpusManifest['groups'] as $group) {
        if (!is_array($group)
            || !is_string($group['path'] ?? null)
            || !safeRelative($group['path'])
            || !is_array($group['files'] ?? null)
        ) {
            $errors[] = 'The Studio corpus manifest carries a malformed group.';
            continue;
        }
        foreach ($group['files'] as $entry) {
            if (!is_array($entry)
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
    "Studio contract verified: release %s, protocol %s, %d schemas, %d corpus files.\n",
    $release['release'],
    $release['protocolVersion'],
    count($schemaManifest['schemas']),
    count($corpusSeen),
);
