<?php

/**
 * Hold the complete Composer-autoloadable surface to its reviewed snapshot.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$snapshotPath = $root . '/resources/public-api.json';
$snapshot = json_decode((string) file_get_contents($snapshotPath), true);
if (!is_array($snapshot)
    || ($snapshot['schema'] ?? null) !== 'kumwe-producer-public-api-v1'
    || !is_array($snapshot['types'] ?? null)
) {
    fwrite(STDERR, "resources/public-api.json is malformed.\n");
    exit(1);
}

$types = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $code = (string) file_get_contents($file->getPathname());
    if (preg_match('/^namespace\s+([^;]+);/m', $code, $namespace) !== 1
        || preg_match(
            '/^(?:final\s+|abstract\s+|readonly\s+)*(class|interface|enum)\s+(\w+)/m',
            $code,
            $declaration,
        ) !== 1
    ) {
        fwrite(STDERR, 'Could not classify public source file: ' . $file->getPathname() . "\n");
        exit(1);
    }
    $types[] = [
        'type' => trim($namespace[1]) . '\\' . $declaration[2],
        'kind' => $declaration[1],
        'file' => str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)),
    ];
}
usort($types, static fn (array $left, array $right): int => strcmp($left['type'], $right['type']));

if ($snapshot['types'] !== $types) {
    $expected = [];
    foreach ($snapshot['types'] as $entry) {
        if (is_array($entry) && is_string($entry['type'] ?? null)) {
            $expected[$entry['type']] = $entry;
        }
    }
    $actual = [];
    foreach ($types as $entry) {
        $actual[$entry['type']] = $entry;
    }
    $errors = [];
    foreach (array_diff_key($expected, $actual) as $type => $_) {
        $errors[] = 'Removed public type: ' . $type;
    }
    foreach (array_diff_key($actual, $expected) as $type => $_) {
        $errors[] = 'Unreviewed public type: ' . $type;
    }
    foreach (array_intersect_key($actual, $expected) as $type => $entry) {
        if ($entry !== $expected[$type]) {
            $errors[] = 'Changed public declaration: ' . $type;
        }
    }
    fwrite(STDERR, "Producer public API verification failed:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo 'Producer public API verified: ' . count($types) . " canonical types.\n";
