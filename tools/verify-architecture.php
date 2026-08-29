<?php

/**
 * Enforce Producer's package and layer boundary without external tooling.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$source = $root . '/src';
$errors = [];
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}
sort($files);

$allowed = [
    'Canonical' => ['Canonical'],
    'Schema' => ['Canonical', 'Schema'],
    'Error' => ['Canonical', 'Error'],
    'Wire' => ['Canonical', 'Error', 'Wire'],
    'Css' => ['Css'],
    'Render' => ['Css', 'Error', 'Render'],
];

foreach ($files as $path) {
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    $code = (string) file_get_contents($path);
    if (!str_contains($code, 'declare(strict_types=1);')) {
        $errors[] = $relative . ' does not enable strict types.';
    }
    if (str_contains($code, 'class_alias(') || str_contains($code, 'Kumwe\\App\\')) {
        $errors[] = $relative . ' introduces a host alias or host-specific dependency.';
    }

    $pathPart = substr($relative, strlen('src/'), -strlen('.php'));
    $segments = explode('/', $pathPart);
    $fileName = array_pop($segments);
    $expectedNamespace = 'Kumwe\\Producer' . ($segments === [] ? '' : '\\' . implode('\\', $segments));
    if (preg_match('/^namespace\s+([^;]+);/m', $code, $namespaceMatch) !== 1
        || trim($namespaceMatch[1]) !== $expectedNamespace
    ) {
        $errors[] = $relative . ' does not match the Producer PSR-4 namespace.';
        continue;
    }
    if (preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|enum)\s+(\w+)/m', $code, $typeMatch) !== 1
        || $typeMatch[1] !== $fileName
    ) {
        $errors[] = $relative . ' must declare the public type named by its file.';
    }

    $owner = $segments[0] ?? $fileName;
    if (!isset($allowed[$owner])) {
        $errors[] = $relative . ' belongs to an unclassified Producer layer.';
        continue;
    }
    preg_match_all('/^use\s+Kumwe\\\\Producer\\\\([A-Za-z]+)\\\\/m', $code, $imports);
    foreach ($imports[1] as $target) {
        if (!in_array($target, $allowed[$owner], true)) {
            $errors[] = sprintf('%s crosses from %s into forbidden %s.', $relative, $owner, $target);
        }
    }
}

$composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
$runtime = is_array($composer['require'] ?? null) ? array_keys($composer['require']) : [];
sort($runtime);
if ($runtime !== ['ext-json', 'ext-mbstring', 'php']) {
    $errors[] = 'composer.json adds a runtime dependency outside PHP, ext-json, and ext-mbstring.';
}

if ($errors !== []) {
    fwrite(STDERR, "Producer architecture verification failed:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo 'Producer architecture verified: ' . count($files) . " source files, no host coupling.\n";
