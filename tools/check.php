<?php

/**
 * Run Producer's complete source-checkout quality lane.
 *
 * Package archives intentionally omit this development runner and every tool
 * it invokes. The shipped Composer metadata exposes only the package smoke.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$php = 'php';
$phpstan = $root . '/vendor/phpstan/phpstan/phpstan.phar';
$phpcs = $root . '/vendor/squizlabs/php_codesniffer/bin/phpcs';
if (!is_file($phpstan) || !is_file($phpcs)) {
    fwrite(STDERR, "The development toolchain is absent; run composer install first.\n");
    exit(1);
}

$commands = [
    [$php, $root . '/tools/lint.php'],
    [$php, $root . '/tools/check-style.php'],
    [$php, $root . '/tools/check-docblocks.php'],
    [$php, $root . '/tools/verify-architecture.php'],
    [$php, $root . '/tools/verify-api.php'],
    [$php, $root . '/tools/verify-api.php', '--self-test'],
    [$php, $root . '/tools/verify-contract.php'],
    [$php, $phpcs, '-q', '-n'],
    [$php, $phpstan, 'analyse', '--no-progress', '--memory-limit=1G', '--debug'],
    [$php, $root . '/smoke.php'],
    [$php, $root . '/tests/run.php'],
];

foreach ($commands as $arguments) {
    $command = implode(' ', array_map('escapeshellarg', $arguments));
    passthru($command, $status);
    if ($status !== 0) {
        exit($status);
    }
}

echo "Producer source quality lane passed.\n";
