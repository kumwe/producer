<?php

/**
 * Enforce repository-wide text hygiene without a formatter dependency.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$directories = ['.github', 'docs', 'examples', 'resources', 'src', 'tests', 'tools'];
$extensions = ['json', 'md', 'php', 'yml', 'yaml'];
$errors = [];
$files = 0;

foreach ($directories as $directory) {
    $path = $root . '/' . $directory;
    if (!is_dir($path)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || !in_array(strtolower($file->getExtension()), $extensions, true)) {
            continue;
        }
        $files++;
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $bytes = (string) file_get_contents($file->getPathname());
        if ($bytes === '' || !str_ends_with($bytes, "\n")) {
            $errors[] = $relative . ' must end with one newline.';
        }
        if (str_contains($bytes, "\r") || str_contains($bytes, "\t")) {
            $errors[] = $relative . ' contains a carriage return or tab.';
        }
        foreach (explode("\n", $bytes) as $number => $line) {
            if (preg_match('/[ ]+$/D', $line) === 1) {
                $errors[] = $relative . ':' . ($number + 1) . ' has trailing whitespace.';
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Producer style verification failed:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo "Producer text style verified: {$files} files.\n";
