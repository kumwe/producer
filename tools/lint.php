<?php

/**
 * Syntax-check every PHP file in the library and its tests.
 *
 * Dependency-free so the lane runs before any composer install.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$count = 0;

foreach (['examples', 'src', 'tests', 'tools'] as $directory) {
    $path = $root . '/' . $directory;
    if (!is_dir($path)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $count++;
        exec(
            escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1',
            $output,
            $status,
        );
        if ($status !== 0) {
            $failures[] = implode("\n", $output);
        }
        $output = [];
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Lint failed:\n" . implode("\n", $failures) . "\n");
    exit(1);
}

echo "Lint passed: {$count} PHP files.\n";
