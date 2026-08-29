<?php

/**
 * Enforce complete member documentation across the library.
 *
 * Every documentable member — class-like declaration, method, non-promoted
 * property, class constant, and enum case — must carry a documentation block
 * ending in an @since tag, and a constructor's block must name every
 * parameter, promoted properties included, with an @param line. A convention
 * without a gate is a suggestion; this is the gate, and it runs in
 * `php tools/check.php` and CI so an undocumented member fails the build.
 *
 * Dependency-free line scanner: deliberate simplicity over a full parser,
 * matching how the library is actually written (one member per declaration
 * line, PSR-12 shapes). A shape it cannot classify fails closed.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$members = 0;

$paths = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->getExtension() === 'php') {
        $paths[] = $file->getPathname();
    }
}
sort($paths);

foreach ($paths as $path) {
    $relative = substr($path, strlen($root) + 1);
    $lines = file($path) ?: [];
    $count = count($lines);

    for ($index = 0; $index < $count; $index++) {
        $line = $lines[$index];

        $isClassLike = preg_match(
            '/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|enum|trait)\s+\w+/',
            $line
        ) === 1;
        $isFunction = preg_match(
            '/^\s*(?:final\s+|abstract\s+)?(?:public|protected|private)\s+(?:static\s+)?function\s+(\w+)/',
            $line,
            $functionMatch
        ) === 1;
        $isConstant = preg_match(
            '/^\s*(?:final\s+)?(?:public|protected|private)\s+const\s/',
            $line
        ) === 1;
        $isEnumCase = preg_match('/^\s*case\s+\w+(\s*=|\s*;)/', $line) === 1;
        $isProperty = !$isFunction && !$isConstant && preg_match(
            '/^\s*(?:public|protected|private)\s+(?:static\s+)?(?:readonly\s+)?[?\\\\\w|]+\s+\$\w+\s*[;=]/',
            $line
        ) === 1;

        if (!$isClassLike && !$isFunction && !$isConstant && !$isEnumCase && !$isProperty) {
            continue;
        }

        // Promoted constructor parameters are covered by the constructor's
        // own block; they are indented inside its signature and end with a
        // comma or closing parenthesis rather than a semicolon.
        if ($isProperty && preg_match('/[,)]\s*$/', rtrim($line)) === 1) {
            continue;
        }

        $members++;
        $where = "{$relative}:" . ($index + 1);

        $back = $index - 1;
        while ($back >= 0 && preg_match('/^\s*(#\[.*\]\s*)?$/', $lines[$back]) === 1) {
            $back--;
        }
        if ($back < 0 || !str_contains($lines[$back], '*/')) {
            $failures[] = "{$where} has no documentation block.";
            continue;
        }

        $block = '';
        for ($start = $back; $start >= 0; $start--) {
            $block = $lines[$start] . $block;
            if (str_contains($lines[$start], '/**')) {
                break;
            }
        }
        if (!preg_match('/@since\s+\d/', $block)) {
            $failures[] = "{$where} documentation block has no @since tag.";
        }

        if ($isFunction) {
            $signature = $line;
            $forward = $index;
            while (!str_contains($signature, ')') && ++$forward < $count) {
                $signature .= $lines[$forward];
            }
            preg_match_all('/\$(\w+)/', preg_replace('/=\s*[^,)]+/', '', substr(
                $signature,
                (int) strpos($signature, '('),
                (int) strrpos($signature, ')') - (int) strpos($signature, '(')
            )) ?? '', $parameters);
            foreach ($parameters[1] as $parameter) {
                if (!preg_match('/@param\s+[^$]*\$' . preg_quote($parameter, '/') . '\b/', $block)) {
                    $failures[] = "{$where} block documents no @param for \${$parameter}.";
                }
            }
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Member documentation is incomplete (" . count($failures) . " finding(s)):\n");
    foreach (array_slice($failures, 0, 40) as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    if (count($failures) > 40) {
        fwrite(STDERR, ' - ... and ' . (count($failures) - 40) . " more.\n");
    }
    exit(1);
}

echo "Member documentation complete: {$members} members across " . count($paths) . " files.\n";
