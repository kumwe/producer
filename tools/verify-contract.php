<?php

/**
 * Prove the vendored Studio contract is exactly the pinned set.
 *
 * Every vendored byte must match the digest PIN.json records, no pinned file
 * may be missing, and no unpinned file may hide in the tree. A contract change
 * reaches this repository only as a deliberate re-pin: new files, new digests,
 * and a new PIN.json in one reviewed change.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

$root = dirname(__DIR__) . '/resources/studio-contract';
$pinPath = $root . '/PIN.json';
$errors = [];

$pin = json_decode((string) file_get_contents($pinPath), true);
if (!is_array($pin) || !isset($pin['files']) || !is_array($pin['files'])) {
    fwrite(STDERR, "PIN.json is missing or malformed.\n");
    exit(1);
}

$pinned = [];
foreach ($pin['files'] as $entry) {
    if (!is_array($entry) || !is_string($entry['file'] ?? null) || !is_string($entry['sha256'] ?? null)) {
        $errors[] = 'PIN.json carries a malformed file entry.';
        continue;
    }
    $pinned[$entry['file']] = $entry['sha256'];
}

$present = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isLink()) {
        $errors[] = 'Symbolic link in the vendored contract: ' . $file->getPathname();
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if ($relative === 'PIN.json') {
        continue;
    }
    $present[$relative] = hash_file('sha256', $file->getPathname());
}

foreach ($pinned as $relative => $digest) {
    if (!isset($present[$relative])) {
        $errors[] = "Pinned file is missing: {$relative}";
    } elseif (!hash_equals($digest, $present[$relative])) {
        $errors[] = "Digest mismatch: {$relative}";
    }
}
foreach ($present as $relative => $digest) {
    if (!isset($pinned[$relative])) {
        $errors[] = "Unpinned file in the vendored contract: {$relative}";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Studio contract verification failed:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

$commit = $pin['source']['commit'] ?? 'unknown';
echo 'Studio contract verified: ' . count($pinned) . " files at {$commit}.\n";
