<?php

/**
 * Validate package-owned evidence against the public API and the real test runner's discovery.
 * This gate checks ownership and discoverability; executing the suite remains a separate required gate.
 * @since 0.1.1
 */

declare(strict_types=1);

namespace Kumwe\Tooling;

use RuntimeException;
use stdClass;
use Throwable;

/**
 * Shared version-one ownership gate, with no runtime or package dependencies.
 * @since 0.1.1
 */
final class TestOwnership
{
    /**
     * @param mixed $value Decoded JSON object.
     * @return array<string, mixed> Object entries.
     * @since 0.1.1
     */
    public static function object(mixed $value): array
    {
        if (!$value instanceof stdClass) {
            throw new RuntimeException('Expected an ownership object.');
        }
        $result = [];
        foreach (get_object_vars($value) as $key => $item) {
            if (!is_string($key)) {
                throw new RuntimeException('Ownership object keys must be strings.');
            }
            $result[$key] = $item;
        }
        return $result;
    }

    /**
     * @param mixed $value Modern symbol map or legacy SDK classification list.
     * @return array<string, mixed> Public symbols, preserving exact ownership keys.
     * @since 0.1.1
     */
    public static function symbols(mixed $value): array
    {
        if ($value instanceof stdClass) {
            return self::object($value);
        }
        if (!is_array($value) || !array_is_list($value)) {
            throw new RuntimeException('Public API must contain a symbol map or classification list.');
        }
        $symbols = [];
        foreach ($value as $entry) {
            $record = self::object($entry);
            $name = self::text($record['type'] ?? null);
            if (array_key_exists($name, $symbols)) {
                throw new RuntimeException('Duplicate classified public type.');
            }
            $symbols[$name] = $entry;
        }
        return $symbols;
    }

    /**
     * @param mixed $value A nonempty string.
     * @return string Validated text.
     * @since 0.1.1
     */
    public static function text(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('Expected nonempty ownership text.');
        }
        return $value;
    }

    /**
     * @param mixed $value A list of strings.
     * @return list<string> Validated list.
     * @since 0.1.1
     */
    public static function strings(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new RuntimeException('Expected an ownership list.');
        }
        return array_map(self::text(...), $value);
    }

    /**
     * @param string $path Repository-relative evidence path.
     * @return void
     * @since 0.1.1
     */
    public static function file(string $path): void
    {
        if (preg_match('~^(?:[a-zA-Z0-9_.-]+/)*[a-zA-Z0-9_.-]+$~D', $path) !== 1) {
            throw new RuntimeException('Invalid evidence path: ' . $path);
        }
        if (array_intersect(['.', '..'], explode('/', $path)) !== [] || !is_file($path)) {
            throw new RuntimeException('Missing or nonlocal evidence: ' . $path);
        }
        if (realpath($path) !== getcwd() . '/' . $path) {
            throw new RuntimeException('Evidence may not traverse a symlink: ' . $path);
        }
    }

    /**
     * @param mixed $value Evidence identifiers.
     * @param array<string, mixed> $inventory Actual runner-discovered identifiers and paths.
     * @return void
     * @since 0.1.1
     */
    public static function tests(mixed $value, array $inventory): void
    {
        $tests = self::strings($value);
        if ($tests === [] || count($tests) !== count(array_unique($tests))) {
            throw new RuntimeException('Evidence must name distinct discovered tests.');
        }
        foreach ($tests as $test) {
            if (!array_key_exists($test, $inventory)) {
                throw new RuntimeException('Evidence is not discovered by the package suite: ' . $test);
            }
            $file = self::text($inventory[$test]);
            if (!str_starts_with($file, 'tests/') || !str_ends_with($file, '.php')) {
                throw new RuntimeException('Behavior evidence must belong to this package: ' . $test);
            }
            self::file($file);
        }
    }

    /**
     * @param stdClass $record Ownership record.
     * @param array<string, mixed> $symbols Exported public API.
     * @param array<string, mixed> $inventory Actual runner discovery.
     * @param string $package Composer package identity.
     * @return void
     * @since 0.1.1
     */
    public static function validate(stdClass $record, array $symbols, array $inventory, string $package): void
    {
        $data = self::object($record);
        if (($data['schema'] ?? null) !== 'kumwe-test-ownership/v1' || ($data['package'] ?? null) !== $package) {
            throw new RuntimeException('Wrong ownership schema or package.');
        }
        $exports = self::object($data['exports'] ?? null);
        $expected = array_keys($symbols);
        $actual = array_keys($exports);
        sort($expected);
        sort($actual);
        if ($expected === [] || $expected !== $actual) {
            throw new RuntimeException('Every exported API symbol must have exactly one ownership entry.');
        }
        foreach ($exports as $entry) {
            $entry = self::object($entry);
            self::tests($entry['behavior'] ?? null, $inventory);
            self::tests($entry['boundary'] ?? null, $inventory);
        }
        $conformance = self::object($data['conformance'] ?? null);
        self::text($conformance['rationale'] ?? null);
        $corpora = self::strings($conformance['corpora'] ?? null);
        if (($conformance['status'] ?? null) === 'owned') {
            self::tests($conformance['tests'] ?? null, $inventory);
            foreach ($corpora as $path) {
                self::file($path);
            }
        } elseif (($conformance['status'] ?? null) === 'not-applicable') {
            if (self::strings($conformance['tests'] ?? null) !== [] || $corpora !== []) {
                throw new RuntimeException('Not-applicable conformance cannot claim tests or corpora.');
            }
        } else {
            throw new RuntimeException('Conformance must be owned or explicitly not applicable.');
        }
        $architecture = self::strings($data['architecture'] ?? null);
        if ($architecture === []) {
            throw new RuntimeException('Package boundary gate evidence is required.');
        }
        foreach ($architecture as $path) {
            self::file($path);
        }
        $host = self::object($data['host'] ?? null);
        self::text($host['repository'] ?? null);
        if (preg_match('/^[a-f0-9]{40}$/D', self::text($host['baseline'] ?? null)) !== 1) {
            throw new RuntimeException('The host extraction baseline must be an exact source commit.');
        }
        if (self::strings($host['retained_responsibilities'] ?? null) === []) {
            throw new RuntimeException('Retained host responsibility must be explicit.');
        }
        $transfers = $host['transfers'] ?? null;
        if (!is_array($transfers) || !array_is_list($transfers)) {
            throw new RuntimeException('Host transfers must be a list, including when empty.');
        }
        foreach ($transfers as $transfer) {
            $transfer = self::object($transfer);
            self::text($transfer['source_test'] ?? null);
            self::text($transfer['retained'] ?? null);
            if (
                !in_array($transfer['action'] ?? null, [
                'remove-on-adoption', 'split-on-adoption', 'retain-until-runtime-cutover',
                ], true)
            ) {
                throw new RuntimeException('A host transfer must name its adoption action.');
            }
            self::tests($transfer['package_tests'] ?? null, $inventory);
        }
    }
}

try {
    chdir(dirname(__DIR__));
    $read = static function (string $path): stdClass {
        TestOwnership::file($path);
        $bytes = file_get_contents($path);
        $value = json_decode($bytes === false ? '' : $bytes, false, 64, JSON_THROW_ON_ERROR);
        if (!$value instanceof stdClass) {
            throw new RuntimeException('Expected a JSON object: ' . $path);
        }
        return $value;
    };
    $record = $read('tests/ownership.json');
    $data = TestOwnership::object($record);
    $api = TestOwnership::object($read(TestOwnership::text($data['api_manifest'] ?? null)));
    $symbols = TestOwnership::symbols($api['symbols'] ?? $api['types'] ?? null);
    $composer = TestOwnership::object($read('composer.json'));
    $package = TestOwnership::text($composer['name'] ?? null);
    $suite = TestOwnership::object($data['suite'] ?? null);
    $command = TestOwnership::strings($suite['inventory_command'] ?? null);
    if ($command === []) {
        throw new RuntimeException('Real test-runner discovery command is required.');
    }
    $scripts = TestOwnership::object($composer['scripts'] ?? null);
    if (
        !(
        $command === ['php', 'tests/run.php', '--list-json']
        && ($scripts['test'] ?? 'php tests/run.php') === 'php tests/run.php'
        ) && !(
        $command === ['php', 'tools/test-inventory.php'] && ($scripts['test'] ?? null) === 'phpunit'
        )
    ) {
        throw new RuntimeException('Ownership inventory must use the package Composer test runner.');
    }
    $lines = [];
    $status = 0;
    exec(implode(' ', array_map(escapeshellarg(...), $command)), $lines, $status);
    if ($status !== 0) {
        throw new RuntimeException('Test discovery failed.');
    }
    $inventory = TestOwnership::object(json_decode(implode("\n", $lines), false, 64, JSON_THROW_ON_ERROR));
    TestOwnership::validate($record, $symbols, $inventory, $package);
    if (in_array('--self-test', $argv ?? [], true)) {
        $first = array_key_first($symbols);
        if ($first === null) {
            throw new RuntimeException('An exported symbol is required.');
        }
        $changes = [
            [['exports', 'Unknown\\Export'], new stdClass()],
            [['exports', $first, 'behavior'], ['MissingTest::testMissing']],
            [['exports', $first, 'boundary'], []],
            [['conformance', 'rationale'], ''],
            [['conformance', 'status'], 'later'],
            [['architecture'], ['tools/missing-boundary-gate.php']],
            [['architecture'], ['tools/../tools/verify-architecture.php']],
            [['host', 'baseline'], 'master'],
            [['host', 'retained_responsibilities'], []],
        ];
        foreach ($changes as [$path, $replacement]) {
            $copy = unserialize(serialize($record), ['allowed_classes' => [stdClass::class]]);
            if (!$copy instanceof stdClass) {
                throw new RuntimeException('Unable to copy fixture.');
            }
            $target = $copy;
            $last = array_pop($path);
            foreach ($path as $part) {
                $next = $target->{$part};
                if (!$next instanceof stdClass) {
                    throw new RuntimeException('Invalid negative fixture path.');
                }
                $target = $next;
            }
            $target->{$last} = $replacement;
            try {
                TestOwnership::validate($copy, $symbols, $inventory, $package);
            } catch (RuntimeException) {
                continue;
            }
            throw new RuntimeException('Negative ownership case did not fail: ' . $last);
        }
        echo count($changes) . " negative ownership cases passed.\n";
    }
    echo count($symbols) . ' public symbols map to ' . count($inventory) . " discovered package tests.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test ownership failed: ' . $error->getMessage() . "\n");
    exit(1);
}
