<?php

/**
 * Exercise commit-object intake, bounded archives, and replacement recovery.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

require_once __DIR__ . '/import-studio-contract.php';

if (($argv[1] ?? null) === '--bomb-child') {
    $path = $argv[2] ?? null;
    if (!is_string($path)) {
        exit(2);
    }
    try {
        $canonical = producerImportFile($path, 'compressed-bomb fixture');
        $bytes = producerImportBytes($canonical);
        producerImportNpmMembers($bytes, ['package/required.txt'], '@test/bomb');
    } catch (RuntimeException $error) {
        if (str_contains($error->getMessage(), 'oversized or non-zero trailing envelope')) {
            echo "Compressed npm bomb failed within its inflated byte bound.\n";
            exit(0);
        }
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }
    fwrite(STDERR, "Compressed npm bomb was unexpectedly accepted.\n");
    exit(1);
}

if (($argv[1] ?? null) === '--oversized-member-child') {
    $path = $argv[2] ?? null;
    if (!is_string($path)) {
        exit(2);
    }
    try {
        $canonical = producerImportFile($path, 'oversized-member fixture');
        $bytes = producerImportBytes($canonical);
        producerImportNpmMembers($bytes, ['package/required.txt'], '@test/oversized-member');
    } catch (RuntimeException $error) {
        if (str_contains($error->getMessage(), 'exceeds its member bounds')) {
            echo "Oversized npm member failed from its declared size.\n";
            exit(0);
        }
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }
    fwrite(STDERR, "Oversized npm member was unexpectedly accepted.\n");
    exit(1);
}

try {
    producerImportSafetyMain();
} catch (Throwable $error) {
    fwrite(STDERR, 'Importer safety verification failed: ' . $error->getMessage() . "\n");
    exit(1);
}

echo "Importer safety verified: commit-only bytes with replace refs disabled, bounded files/npm tar, "
    . "links refused, checked rollback, and committed-install cleanup semantics.\n";

/** Run every isolated importer safety regression. */
function producerImportSafetyMain(): void
{
    $root = sys_get_temp_dir() . '/producer-import-safety-' . bin2hex(random_bytes(8));
    if (!mkdir($root, 0700)) {
        throw new RuntimeException('Could not create the importer safety root.');
    }
    try {
        producerImportSafetyCommitObjects($root);
        producerImportSafetyPathsAndBounds($root);
        producerImportSafetyNpmArchives($root);
        producerImportSafetyReplacement($root);
    } finally {
        if (is_dir($root) && !is_link($root)) {
            producerImportRemoveTree($root, dirname($root));
        }
    }
}

/** Prove dirty checkout bytes cannot alter manifest-governed import output. */
function producerImportSafetyCommitObjects(string $root): void
{
    $repository = $root . '/studio';
    $schemaDirectory = $repository . '/packages/protocol/schemas';
    if (!mkdir($schemaDirectory, 0700, true)) {
        throw new RuntimeException('Could not create the commit-object fixture.');
    }
    $cleanSchema = "{\"kind\":\"clean\"}\n";
    $dirtySchema = "{\"kind\":\"dirty\"}\n";
    $manifestPath = $schemaDirectory . '/manifest.json';
    $schemaPath = $schemaDirectory . '/sample.schema.json';
    $cleanManifest = producerImportJson([
        'kind' => 'schema-manifest',
        'schemas' => [[
            'file' => 'sample.schema.json',
            'digest' => producerImportSri($cleanSchema),
        ]],
    ]);
    $dirtyManifest = producerImportJson([
        'kind' => 'schema-manifest',
        'schemas' => [[
            'file' => 'sample.schema.json',
            'digest' => producerImportSri($dirtySchema),
        ]],
    ]);
    file_put_contents($manifestPath, $cleanManifest);
    file_put_contents($schemaPath, $cleanSchema);
    mkdir($repository . '/nested', 0700);
    file_put_contents($repository . '/nested/member.txt', "ordinary\n");
    file_put_contents($repository . '/oversized.txt', "12345\n");

    producerImportGitCommand($repository, ['init', '--quiet'], 1024);
    producerImportGitCommand($repository, ['config', 'user.name', 'Importer Safety'], 1024);
    producerImportGitCommand($repository, ['config', 'user.email', 'safety@example.invalid'], 1024);
    producerImportGitCommand($repository, ['add', '--', '.'], 1024);
    producerImportGitCommand($repository, ['commit', '--quiet', '-m', 'fixture'], 1024);
    $commit = producerImportGitCommit($repository);

    file_put_contents($manifestPath, $dirtyManifest);
    file_put_contents($schemaPath, $dirtySchema);
    producerImportGitCommand($repository, ['add', '--', '.'], 1024);
    producerImportGitCommand($repository, ['commit', '--quiet', '-m', 'replacement'], 1024);
    $replacement = producerImportGitCommit($repository);
    producerImportGitCommand($repository, ['replace', $commit, $replacement], 1024);
    $replaceRef = producerImportGitCommand(
        $repository,
        ['show-ref', '--verify', 'refs/replace/' . $commit],
        128,
    );
    producerImportSafetyRequire(
        $replaceRef === $replacement . ' refs/replace/' . $commit . "\n",
        'Exact replacement-ref adversarial fixture was not installed.',
    );
    producerImportGitCommand($repository, ['reset', '--hard', $commit], 1024);
    producerImportSafetyRequire(
        producerImportGitCommit($repository) === $commit,
        'Replacement-ref fixture did not restore the evidence commit as HEAD.',
    );

    file_put_contents($manifestPath, $dirtyManifest);
    file_put_contents($schemaPath, $dirtySchema);
    file_put_contents($repository . '/attacker.txt', "untracked\n");
    $tree = producerImportGitTree($repository, $commit);
    $manifestBytes = producerImportGitBlob(
        $repository,
        $tree,
        'packages/protocol/schemas/manifest.json',
    );
    $manifest = producerImportObject($manifestBytes, 'commit fixture manifest');
    $expected = [];
    $count = producerImportSchemas($repository, $tree, $manifest, $expected);
    $imported = $expected['protocol/schemas/sample.schema.json'] ?? null;
    producerImportSafetyRequire($count === 1, 'Commit schema manifest count changed through dirty bytes.');
    producerImportSafetyRequire(
        $imported === $cleanSchema,
        'Dirty tracked schema and adjusted manifest affected imported commit bytes.',
    );
    producerImportSafetyRequire(
        hash('sha256', (string) $imported) === hash('sha256', $cleanSchema),
        'Dirty checkout bytes affected the generated direct-file PIN digest.',
    );
    producerImportSafetyRequire(
        producerImportGitCommit($repository) === $commit,
        'Dirty checkout changed the retained exact HEAD identity.',
    );
    producerImportSafetyThrows(
        static fn (): string => producerImportGitBlob($repository, $tree, 'missing.json'),
        'missing required blob',
    );
    producerImportSafetyThrows(
        static fn (): string => producerImportGitBlob($repository, $tree, 'nested'),
        'not an ordinary data blob',
    );
    producerImportSafetyThrows(
        static fn (): string => producerImportGitBlob($repository, $tree, 'oversized.txt', 4),
        'exceeds its byte bound',
    );
}

/** Prove canonical input identity and pre-allocation file limits. */
function producerImportSafetyPathsAndBounds(string $root): void
{
    $ordinary = $root . '/ordinary';
    mkdir($ordinary, 0700);
    file_put_contents($ordinary . '/input.json', "{}\n");
    symlink($ordinary, $root . '/ancestor-link');
    symlink($ordinary . '/input.json', $root . '/file-link.json');
    producerImportSafetyThrows(
        static fn (): string => producerImportDirectory($root . '/ancestor-link/', 'linked directory'),
        'symbolic link',
    );
    producerImportSafetyThrows(
        static fn (): string => producerImportFile(
            $root . '/ancestor-link/input.json',
            'ancestor-linked file',
        ),
        'symbolic link',
    );
    producerImportSafetyThrows(
        static fn (): string => producerImportFile($root . '/file-link.json', 'linked file'),
        'symbolic link',
    );
    producerImportSafetyRequire(
        producerImportFile($ordinary . '/input.json', 'ordinary file') === $ordinary . '/input.json',
        'Canonical ordinary input did not retain its exact identity.',
    );

    $sparse = $root . '/oversized-sparse.bin';
    $handle = fopen($sparse, 'w+b');
    if (!is_resource($handle) || !ftruncate($handle, PRODUCER_IMPORT_MAX_BYTES + 1)) {
        throw new RuntimeException('Could not create the oversized sparse fixture.');
    }
    fclose($handle);
    producerImportSafetyThrows(
        static fn (): string => producerImportBytes($sparse),
        'exceeds its byte bound',
    );
}

/** Prove gzip bombs and every non-regular tar type fail before allocation. */
function producerImportSafetyNpmArchives(string $root): void
{
    foreach (["\0", '1', '2', '3', '4', '5', '6', '7', 'K', 'L', 'g', 'x'] as $type) {
        $tar = producerImportSafetyTarHeader('package/required.txt', 0, $type) . str_repeat("\0", 1024);
        $archive = gzencode($tar, 9, ZLIB_ENCODING_GZIP);
        if (!is_string($archive)) {
            throw new RuntimeException('Could not encode the special-member npm fixture.');
        }
        producerImportSafetyThrows(
            static fn (): array => producerImportNpmMembers(
                $archive,
                ['package/required.txt'],
                '@test/special',
            ),
            'link, special member',
        );
    }

    $oversizedMember = $root . '/npm-oversized-member.tgz';
    $oversizedTar = producerImportSafetyTarHeader(
        'package/required.txt',
        PRODUCER_IMPORT_MAX_BYTES + 1,
        '0',
    ) . str_repeat("\0", 1024);
    $oversizedArchive = gzencode($oversizedTar, 9, ZLIB_ENCODING_GZIP);
    if (!is_string($oversizedArchive)) {
        throw new RuntimeException('Could not encode the oversized-member npm fixture.');
    }
    file_put_contents($oversizedMember, $oversizedArchive);
    [$status, $stdout, $stderr] = producerImportSafetyProcess([
        PHP_BINARY,
        '-d',
        'memory_limit=32M',
        __FILE__,
        '--oversized-member-child',
        $oversizedMember,
    ]);
    producerImportSafetyRequire(
        $status === 0
            && str_contains($stdout, 'failed from its declared size')
            && $stderr === '',
        'Oversized npm member was not rejected from its declared size under the 32 MiB memory limit.',
    );

    $bomb = $root . '/npm-compressed-bomb.tgz';
    $gzip = gzopen($bomb, 'wb9');
    if ($gzip === false) {
        throw new RuntimeException('Could not create the compressed npm bomb fixture.');
    }
    $zeroMegabyte = str_repeat("\0", 1048576);
    for ($index = 0; $index < 40; $index++) {
        if (gzwrite($gzip, $zeroMegabyte) !== strlen($zeroMegabyte)) {
            throw new RuntimeException('Could not write the compressed npm bomb fixture.');
        }
    }
    gzclose($gzip);
    [$status, $stdout, $stderr] = producerImportSafetyProcess([
        PHP_BINARY,
        '-d',
        'memory_limit=32M',
        __FILE__,
        '--bomb-child',
        $bomb,
    ]);
    producerImportSafetyRequire(
        $status === 0 && str_contains($stdout, 'failed within its inflated byte bound') && $stderr === '',
        'Compressed npm bomb did not fail closed under the 32 MiB memory limit.',
    );
}

/** Prove checked rollback and non-failing post-commit cleanup semantics. */
function producerImportSafetyReplacement(string $root): void
{
    $parent = $root . '/transactions';
    $scope = $parent . '/scope';
    mkdir($scope, 0700, true);

    $target = $scope . '/contract';
    producerImportSafetyOldTarget($target);
    producerImportReplace(['new.txt' => "new\n"], $scope . '/../scope/contract');
    producerImportSafetyRequire(
        file_get_contents($target . '/new.txt') === "new\n",
        'Normalized production-style target spelling did not install.',
    );
    producerImportSafetyRequire(
        producerImportSafetyGeneratedTrees($scope) === [],
        'Successful replacement left a generated transaction tree.',
    );
    producerImportRemoveTree($target, $scope);

    producerImportSafetyOldTarget($target);
    $calls = 0;
    $rename = static function (string $from, string $to) use (&$calls): bool {
        $calls++;
        if ($calls === 2) {
            return false;
        }

        return rename($from, $to);
    };
    producerImportSafetyThrows(
        static function () use ($target, $rename): void {
            producerImportReplace(['new.txt' => "new\n"], $target, $rename);
        },
        'prior generation was restored',
    );
    producerImportSafetyRequire(
        file_get_contents($target . '/old.txt') === "old\n"
            && producerImportSafetyGeneratedTrees($scope) === [],
        'Checked rollback did not restore the old target and clean the candidate.',
    );
    producerImportRemoveTree($target, $scope);

    producerImportSafetyOldTarget($target);
    $calls = 0;
    $rename = static function (string $from, string $to) use (&$calls): bool {
        $calls++;
        if ($calls === 2 || $calls === 3) {
            return false;
        }

        return rename($from, $to);
    };
    producerImportSafetyThrows(
        static function () use ($target, $rename): void {
            producerImportReplace(['new.txt' => "new\n"], $target, $rename);
        },
        'recovery required',
    );
    $generated = producerImportSafetyGeneratedTrees($scope);
    producerImportSafetyRequire(
        !file_exists($target) && count($generated) === 2,
        'Rollback failure did not preserve both explicit generated recovery trees.',
    );
    $backup = producerImportSafetyGeneratedTree($generated, '.studio-contract-backup-');
    $candidate = producerImportSafetyGeneratedTree($generated, '.studio-contract-import-');
    producerImportSafetyRequire(
        file_get_contents($backup . '/old.txt') === "old\n"
            && file_get_contents($candidate . '/new.txt') === "new\n",
        'Rollback failure recovery trees do not preserve both generations.',
    );
    rename($backup, $target);
    producerImportRemoveTree($candidate, $scope);
    producerImportRemoveTree($target, $scope);

    producerImportSafetyOldTarget($target);
    $warnings = [];
    $remove = static function (string $tree, string $requiredParent): void {
        if (str_starts_with(basename($tree), '.studio-contract-backup-')) {
            throw new RuntimeException('injected committed-backup cleanup failure');
        }
        producerImportRemoveTree($tree, $requiredParent);
    };
    $warning = static function (string $message) use (&$warnings): void {
        $warnings[] = $message;
    };
    producerImportReplace(['new.txt' => "new\n"], $target, null, $remove, $warning);
    $generated = producerImportSafetyGeneratedTrees($scope);
    producerImportSafetyRequire(
        file_get_contents($target . '/new.txt') === "new\n"
            && count($generated) === 1
            && count($warnings) === 1
            && str_contains($warnings[0], 'new generation is installed'),
        'Post-commit cleanup failure became a false failed import or lost its warning.',
    );
    producerImportRemoveTree($generated[0], $scope);
    producerImportRemoveTree($target, $scope);

    $realTarget = $scope . '/real-target';
    producerImportSafetyOldTarget($realTarget);
    symlink($realTarget, $target);
    producerImportSafetyThrows(
        static function () use ($target): void {
            producerImportReplace(['new.txt' => "new\n"], $target);
        },
        'not one ordinary scoped directory',
    );
    producerImportSafetyRequire(
        file_get_contents($realTarget . '/old.txt') === "old\n",
        'Linked replacement target changed its referent.',
    );
    unlink($target);
    producerImportRemoveTree($realTarget, $scope);
}

/** Create one old contract target. */
function producerImportSafetyOldTarget(string $target): void
{
    if (!mkdir($target, 0700)) {
        throw new RuntimeException('Could not create an old transaction target.');
    }
    file_put_contents($target . '/old.txt', "old\n");
}

/** @return list<string> */
function producerImportSafetyGeneratedTrees(string $parent): array
{
    $entries = scandir($parent);
    if (!is_array($entries)) {
        throw new RuntimeException('Could not inspect generated transaction trees.');
    }
    $trees = [];
    foreach ($entries as $entry) {
        if (
            str_starts_with($entry, '.studio-contract-import-')
            || str_starts_with($entry, '.studio-contract-backup-')
        ) {
            $trees[] = $parent . '/' . $entry;
        }
    }
    sort($trees, SORT_STRING);

    return $trees;
}

/** Select exactly one generated tree with a reviewed prefix. */
function producerImportSafetyGeneratedTree(array $trees, string $prefix): string
{
    $matches = array_values(array_filter(
        $trees,
        static fn (string $tree): bool => str_starts_with(basename($tree), $prefix),
    ));
    if (count($matches) !== 1) {
        throw new RuntimeException('Generated recovery tree set is ambiguous.');
    }

    return $matches[0];
}

/** Build one npm-style ustar header for hostile special-type fixtures. */
function producerImportSafetyTarHeader(string $path, int $size, string $type): string
{
    $header = str_repeat("\0", 512);
    $header = substr_replace($header, $path, 0, strlen($path));
    $header = substr_replace($header, sprintf('%010o', $size) . " \0", 124, 12);
    $header = substr_replace($header, str_repeat(' ', 8), 148, 8);
    $header[156] = $type;
    $header = substr_replace($header, "ustar\0", 257, 6);
    $header = substr_replace($header, '00', 263, 2);
    $octets = unpack('C*', $header);
    if (!is_array($octets)) {
        throw new RuntimeException('Could not checksum a hostile tar header.');
    }
    $checksum = sprintf('%06o', array_sum($octets)) . " \0";

    return substr_replace($header, $checksum, 148, 8);
}

/** @return array{0: int, 1: string, 2: string} */
function producerImportSafetyProcess(array $command): array
{
    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        null,
        null,
        ['bypass_shell' => true],
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the constrained-memory safety child.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1], 65537);
    $stderr = stream_get_contents($pipes[2], 65537);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if (!is_string($stdout) || !is_string($stderr) || strlen($stdout) > 65536 || strlen($stderr) > 65536) {
        throw new RuntimeException('Constrained-memory safety child exceeded its output bound.');
    }

    return [$status, $stdout, $stderr];
}

/** Require one adversarial invariant. */
function producerImportSafetyRequire(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Require one callable to throw a source-bearing RuntimeException. */
function producerImportSafetyThrows(callable $operation, string $messageFragment): void
{
    try {
        $operation();
    } catch (RuntimeException $error) {
        if (str_contains($error->getMessage(), $messageFragment)) {
            return;
        }
        throw new RuntimeException(
            'Safety failure had the wrong reason: ' . $error->getMessage(),
            0,
            $error,
        );
    }
    throw new RuntimeException('Safety operation was unexpectedly accepted: ' . $messageFragment);
}
