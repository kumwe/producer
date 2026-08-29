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
$schemas = archiveJsonObject($contract . '/protocol/schemas/manifest.json', $errors);
$corpus = archiveJsonObject($contract . '/testkit/corpus-manifest.json', $errors);

$expected = array_fill_keys([
    'LICENSE',
    'README.md',
    'composer.json',
    'resources/public-api.json',
    'resources/studio-contract/PIN.json',
    'resources/studio-contract/protocol/schemas/manifest.json',
    'resources/studio-contract/protocol/studio-release.json',
    'resources/studio-contract/studio-release.json',
    'resources/studio-contract/testkit/corpus-manifest.json',
    'resources/studio-contract/testkit/studio-release.json',
    'smoke.php',
], true);

$types = is_array($snapshot['types'] ?? null) ? $snapshot['types'] : [];
if (($snapshot['schema'] ?? null) !== 2 || count($types) !== 67) {
    $errors[] = 'resources/public-api.json must name exactly 67 reviewed public types.';
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
if (count($schemaEntries) !== 47) {
    $errors[] = 'The Studio schema manifest must name exactly 47 schemas.';
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
if ($corpusCount !== 282) {
    $errors[] = 'The Studio corpus manifest must name exactly 282 files.';
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
if (count($expected) !== 407) {
    $errors[] = 'The reviewed package file set no longer totals exactly 407 files.';
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

echo "Composer archive verified: 407 files, 67 public types, 47 Studio schemas, 282 corpus files.\n";
