<?php

/**
 * Dependency-free test runner: discovers tests/Case/*Test.php, runs every
 * public method beginning with "test", and reports one line per file.
 *
 * Assertions come from Kumwe\Producer\Tests\TestCase. No framework, so the
 * suite runs on any PHP >= 8.1 with no composer install.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Kumwe\\Producer\\Tests\\' => __DIR__ . '/',
        'Kumwe\\Producer\\' => dirname(__DIR__) . '/src/',
    ];
    foreach ($prefixes as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $path = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) {
                require $path;
            }
            return;
        }
    }
});

$caseDirectory = __DIR__ . '/Case';
$files = glob($caseDirectory . '/*Test.php') ?: [];
sort($files);

// Discovery is shared by execution and the package ownership gate.
$inventory = [];
foreach ($files as $file) {
    $class = 'Kumwe\\Producer\\Tests\\Case\\' . basename($file, '.php');
    if (!class_exists($class) || !is_subclass_of($class, 'Kumwe\\Producer\\Tests\\TestCase')) {
        fwrite(STDERR, "Invalid test case: {$file}\n");
        exit(1);
    }
    $methods = array_filter(get_class_methods($class), static fn (string $name): bool => str_starts_with($name, 'test'));
    if ($methods === []) {
        fwrite(STDERR, "Empty test case: {$file}\n");
        exit(1);
    }
    foreach ($methods as $method) {
        $inventory[$class . '::' . $method] = 'tests/Case/' . basename($file);
    }
}
if ($inventory === []) {
    fwrite(STDERR, "No test methods were discovered.\n");
    exit(1);
}
if (($argv[1] ?? null) === '--list-json') {
    ksort($inventory);
    echo json_encode($inventory, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

$totalTests = 0;
$totalAssertions = 0;
$failures = [];

if ($files === []) {
    $failures[] = 'No test files were discovered.';
}

foreach ($files as $file) {
    $class = 'Kumwe\\Producer\\Tests\\Case\\' . basename($file, '.php');
    if (!class_exists($class)) {
        $failures[] = "{$file} declares no {$class}.";
        continue;
    }
    $case = new $class();
    $ran = 0;
    foreach (get_class_methods($case) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        $totalTests++;
        $ran++;
        try {
            $case->{$method}();
        } catch (\Throwable $error) {
            $failures[] = sprintf(
                '%s::%s — %s (%s:%d)',
                $class,
                $method,
                $error->getMessage(),
                basename($error->getFile()),
                $error->getLine()
            );
        }
    }
    if ($ran === 0) {
        $failures[] = "{$file} declares no public test methods.";
    }
    $totalAssertions += $case->assertionCount();
    echo sprintf("%-52s %3d tests\n", basename($file), $ran);
}

if ($failures !== []) {
    fwrite(STDERR, "\nFailures:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "\nProducer suite passed: {$totalTests} tests, {$totalAssertions} assertions.\n";
