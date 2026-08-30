<?php

/**
 * Prove an extracted Composer archive has the exact runtime boundary.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

$candidate = $argv[1] ?? null;
if (!is_string($candidate)) {
    fwrite(STDERR, "Usage: php tools/verify-archive.php EXTRACTED_ARCHIVE_ROOT\n");
    exit(1);
}
$root = realpath($candidate);
if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "Usage: php tools/verify-archive.php EXTRACTED_ARCHIVE_ROOT\n");
    exit(1);
}

/** Whether a manifest member is a normalized package-relative path. */
function archiveSafeRelative(mixed $path): bool
{
    if (
        !is_string($path)
        || $path === ''
        || str_starts_with($path, '/')
        || str_contains($path, '\\')
    ) {
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
 * Decode one required JSON object as an associative array.
 *
 * @param list<string> $errors Collected archive findings.
 *
 * @return array<string, mixed>
 */
function archiveJsonObject(string $path, array &$errors): array
{
    try {
        $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        $errors[] = $path . ' is not readable JSON: ' . $error->getMessage();

        return [];
    }
    if (!is_array($value) || array_is_list($value)) {
        $errors[] = $path . ' must contain a JSON object.';

        return [];
    }

    $object = [];
    foreach ($value as $member => $item) {
        if (!is_string($member)) {
            $errors[] = $path . ' contains a non-string object member.';

            return [];
        }
        $object[$member] = $item;
    }

    return $object;
}

/** @var list<string> $errors */
$errors = [];
$actual = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo) {
        $errors[] = 'The archive iterator returned a non-file entry.';
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if ($file->isLink()) {
        $errors[] = 'The archive contains a symbolic link: ' . $relative;
        continue;
    }
    if ($file->isFile()) {
        $actual[$relative] = true;
    }
}

$composer = archiveJsonObject($root . '/composer.json', $errors);
$snapshot = archiveJsonObject($root . '/resources/public-api.json', $errors);
$contract = $root . '/resources/studio-contract';
$pin = archiveJsonObject($contract . '/PIN.json', $errors);
$schemas = archiveJsonObject($contract . '/protocol/schemas/manifest.json', $errors);
$corpus = archiveJsonObject($contract . '/testkit/corpus-manifest.json', $errors);
$browser = archiveJsonObject($contract . '/browser/studio-assets.json', $errors);

$expected = array_fill_keys([
    'LICENSE',
    'README.md',
    'composer.json',
    'resources/public-api.json',
    'resources/studio-contract/PIN.json',
    'resources/studio-contract/browser/studio-assets.json',
    'resources/studio-contract/protocol/schemas/manifest.json',
    'resources/studio-contract/protocol/studio-release.json',
    'resources/studio-contract/studio-release.json',
    'resources/studio-contract/testkit/corpus-manifest.json',
    'resources/studio-contract/testkit/studio-release.json',
    'smoke.php',
], true);

$types = is_array($snapshot['types'] ?? null) ? $snapshot['types'] : [];
if (($snapshot['schema'] ?? null) !== 2 || count($types) !== 70) {
    $errors[] = 'resources/public-api.json must name exactly 70 reviewed public types.';
}
foreach ($types as $type => $entry) {
    $prefix = 'Kumwe\\Producer\\';
    if (
        !is_string($type)
        || !str_starts_with($type, $prefix)
        || !is_array($entry)
        || !in_array($entry['kind'] ?? null, ['class', 'interface', 'enum'], true)
    ) {
        $errors[] = 'The public API manifest carries a malformed type entry.';
        continue;
    }
    $relative = 'src/' . str_replace('\\', '/', substr($type, strlen($prefix))) . '.php';
    if (isset($expected[$relative])) {
        $errors[] = 'The public API manifest repeats a source path: ' . $relative;
    }
    $expected[$relative] = true;
}

$schemaEntries = is_array($schemas['schemas'] ?? null) ? $schemas['schemas'] : [];
if (count($schemaEntries) !== 55) {
    $errors[] = 'The Studio schema manifest must name exactly 55 schemas.';
}
foreach ($schemaEntries as $entry) {
    $file = is_array($entry) ? ($entry['file'] ?? null) : null;
    if (!is_string($file) || !archiveSafeRelative($file)) {
        $errors[] = 'The Studio schema manifest carries an unsafe file path.';
        continue;
    }
    $relative = 'resources/studio-contract/protocol/schemas/' . $file;
    if (isset($expected[$relative])) {
        $errors[] = 'The Studio schema manifest repeats: ' . $file;
    }
    $expected[$relative] = true;
}

$corpusCount = 0;
$groups = is_array($corpus['groups'] ?? null) ? $corpus['groups'] : [];
foreach ($groups as $group) {
    $path = is_array($group) ? ($group['path'] ?? null) : null;
    $files = is_array($group) ? ($group['files'] ?? null) : null;
    if (!is_string($path) || !archiveSafeRelative($path) || !is_array($files)) {
        $errors[] = 'The Studio corpus manifest carries a malformed group.';
        continue;
    }
    foreach ($files as $entry) {
        $file = is_array($entry) ? ($entry['file'] ?? null) : null;
        if (!is_string($file) || !archiveSafeRelative($file)) {
            $errors[] = 'The Studio corpus manifest carries an unsafe file path.';
            continue;
        }
        $relative = 'resources/studio-contract/testkit/' . $path . '/' . $file;
        if (isset($expected[$relative])) {
            $errors[] = 'The Studio corpus manifest repeats: ' . $relative;
        }
        $expected[$relative] = true;
        $corpusCount++;
    }
}
if ($corpusCount !== 301) {
    $errors[] = 'The Studio corpus manifest must name exactly 301 files.';
}

$browserPin = is_array($pin['browser_artifacts'] ?? null) ? $pin['browser_artifacts'] : [];
$resolvedAssets = is_array($browserPin['resolved_assets'] ?? null)
    ? $browserPin['resolved_assets']
    : [];
$redistributionFiles = is_array($browserPin['redistribution_files'] ?? null)
    ? $browserPin['redistribution_files']
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
) {
    $errors[] = 'The Studio PIN does not carry the exact browser proof members.';
}
$assetRoles = [];
$contractDirectFiles = [
    'studio-release.json',
    'protocol/studio-release.json',
    'protocol/schemas/manifest.json',
    'testkit/studio-release.json',
    'testkit/corpus-manifest.json',
    'browser/studio-assets.json',
];
foreach ($resolvedAssets as $entry) {
    $role = is_array($entry) ? ($entry['role'] ?? null) : null;
    $file = is_array($entry) ? ($entry['file'] ?? null) : null;
    if (
        !in_array($role, ['browser-module', 'enhancement-runtime'], true)
        || isset($assetRoles[$role])
        || !is_string($file)
        || !str_starts_with($file, 'browser/assets/')
        || !archiveSafeRelative($file)
    ) {
        $errors[] = 'The Studio PIN carries a malformed resolved browser asset.';
        continue;
    }
    $assetRoles[$role] = true;
    $expected['resources/studio-contract/' . $file] = true;
    $contractDirectFiles[] = $file;
}
$browserRelease = is_array($browser['release'] ?? null) ? $browser['release'] : [];
if (
    array_keys($assetRoles) !== ['browser-module', 'enhancement-runtime']
    || ($browserRelease['version'] ?? null) !== '0.1.0-beta.2'
) {
    $errors[] = 'The package must contain both exact Studio beta.2 browser runtime assets.';
}

$manifestAssets = is_array($browser['assets'] ?? null) ? $browser['assets'] : [];
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
        $errors[] = 'The Studio browser manifest repeats or malforms a redistribution path.';
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
    $errors[] = 'The archive must carry the complete 14-file Studio redistribution closure.';
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
    $file = is_string($assetPath) ? 'browser/' . $assetPath : 'browser/invalid';
    $path = $contract . '/' . $file;
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
        || !archiveSafeRelative($assetPath)
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
        || ($binding['file'] ?? null) !== $file
        || ($binding['package'] ?? null) !== '@kumwe/studio'
        || ($binding['package_path'] ?? null) !== 'dist/browser/' . $assetPath
        || ($binding['bytes'] ?? null) !== ($asset['bytes'] ?? null)
        || ($binding['media_type'] ?? null) !== ($asset['mediaType'] ?? null)
        || ($binding['integrity'] ?? null) !== ($asset['integrity'] ?? null)
        || ($binding['sha256'] ?? null) !== $sha256
    ) {
        $errors[] = 'The archived Studio redistribution file is missing or disagrees: ' . $file;
        continue;
    }
    $expected['resources/studio-contract/' . $file] = true;
    $contractDirectFiles[] = $file;
}

$directEntries = is_array($pin['files'] ?? null) ? $pin['files'] : [];
$directSeen = [];
foreach ($directEntries as $entry) {
    $file = is_array($entry) ? ($entry['file'] ?? null) : null;
    $sha256 = is_array($entry) ? ($entry['sha256'] ?? null) : null;
    if (
        !is_string($file)
        || !archiveSafeRelative($file)
        || !is_string($sha256)
        || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1
        || isset($directSeen[$file])
    ) {
        $errors[] = 'The Studio PIN carries a malformed or repeated direct-file binding.';
        continue;
    }
    $path = $contract . '/' . $file;
    if (!is_file($path) || !hash_equals($sha256, (string) hash_file('sha256', $path))) {
        $errors[] = 'The archived Studio direct-file digest differs: ' . $file;
    }
    $directSeen[$file] = true;
}
sort($contractDirectFiles, SORT_STRING);
$actualDirectFiles = array_keys($directSeen);
sort($actualDirectFiles, SORT_STRING);
if ($actualDirectFiles !== $contractDirectFiles) {
    $errors[] = 'The archive PIN direct-file set is incomplete or expanded.';
}

if (($composer['autoload'] ?? null) !== ['psr-4' => ['Kumwe\\Producer\\' => 'src/']]) {
    $errors[] = 'The archive Composer autoloader is not the one canonical Producer namespace.';
}
if (isset($composer['autoload-dev'])) {
    $errors[] = 'The archive Composer metadata exposes an absent development autoloader.';
}
if (($composer['scripts'] ?? null) !== ['cs' => 'phpcs -q -n', 'smoke' => 'php smoke.php']) {
    $errors[] = 'The archive Composer scripts may name only PHPCS and the shipped package smoke.';
}

$roots = [];
foreach (array_keys($actual) as $relative) {
    $roots[explode('/', $relative, 2)[0]] = true;
}
$actualRoots = array_keys($roots);
sort($actualRoots);
$expectedRoots = ['LICENSE', 'README.md', 'composer.json', 'resources', 'smoke.php', 'src'];
if ($actualRoots !== $expectedRoots) {
    $errors[] = 'Archive roots differ: ' . implode(', ', $actualRoots);
}
if (count($expected) !== 454) {
    $errors[] = 'The reviewed package file set no longer totals exactly 454 files.';
}
foreach (array_diff_key($expected, $actual) as $relative => $_) {
    $errors[] = 'Required archive file is missing: ' . $relative;
}
foreach (array_diff_key($actual, $expected) as $relative => $_) {
    $errors[] = 'Unexpected archive file: ' . $relative;
}

if ($errors !== []) {
    fwrite(STDERR, "Composer archive verification failed:\n - " . implode("\n - ", array_unique($errors)) . "\n");
    exit(1);
}

echo "Composer archive verified: 454 files, 70 public types, 55 Studio schemas, 301 corpus files, "
    . "14 redistribution files.\n";
