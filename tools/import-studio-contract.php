<?php

/**
 * Import one immutable Studio generation from manifests and published npm bits.
 *
 * Usage:
 * php tools/import-studio-contract.php STUDIO_ROOT EVIDENCE_JSON STUDIO_TGZ
 *     RENDERER_TGZ BROWSER_TAR BROWSER_SHA256
 *
 * The Studio checkout supplies the exact evidence commit object and HEAD
 * identity; release, schema, and testkit bytes are read from that object,
 * never from working-tree files. Browser bytes come from the two
 * provenance-backed npm tarballs named by the release record. The importer
 * verifies every source manifest entry, both tarball envelopes, the asset manifest, the two
 * selected browser roles, the complete manifest-declared redistribution
 * notice/license closure, and the non-vendored deterministic outer browser
 * archive plus its detached checksum before replacing
 * resources/studio-contract.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

const PRODUCER_IMPORT_ROOT = __DIR__ . '/../resources/studio-contract';
const PRODUCER_IMPORT_MAX_BYTES = 16777216;
const PRODUCER_IMPORT_BROWSER_ARCHIVE_BYTES = 1401344;
const PRODUCER_IMPORT_BROWSER_ARCHIVE_BUDGET_BYTES = 2097152;
const PRODUCER_IMPORT_BROWSER_ARCHIVE_MEMBERS = 74;
const PRODUCER_IMPORT_BROWSER_ARCHIVE_SHA256 =
    'e1bd88fa0bf6170e098bb50235783137d8d1aea9b28421a700b550886ffbab01';
const PRODUCER_IMPORT_BROWSER_ARCHIVE_SHA512 =
    '630a33ebf6ea0321559fdc78644459225544f1621c91e442ced8a357a1d68501'
    . 'd09715f5c660526be819532488fc80fa5c53536ab9060e68bac80fdbd90ed764';
const PRODUCER_IMPORT_BROWSER_MANIFEST_SHA256 =
    '89cd32a0e30075853d06056855c61f814be061b5a6fe2021b87d37db0c4fde68';
const PRODUCER_IMPORT_BROWSER_CHECKSUM_BYTES = 115;
const PRODUCER_IMPORT_BROWSER_CHECKSUM_SHA256 =
    '25d9d43978e9bf156422794f668dcceba612e22c7ee2236b47f78d066434ddf0';
const PRODUCER_IMPORT_NPM_UNCOMPRESSED_MAX_BYTES = 33554432;
const PRODUCER_IMPORT_NPM_MAX_MEMBERS = 4096;

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try {
        exit(producerImportMain($_SERVER['argv'] ?? []));
    } catch (Throwable $error) {
        fwrite(STDERR, "Studio contract import failed: {$error->getMessage()}\n");
        exit(1);
    }
}

/**
 * Import and atomically replace the exact contract generation.
 *
 * @param list<string> $arguments Command plus six required paths.
 *
 * @since 0.2.0
 */
function producerImportMain(array $arguments): int
{
    if (count($arguments) !== 7) {
        fwrite(
            STDERR,
            "Usage: php tools/import-studio-contract.php "
                . "STUDIO_ROOT EVIDENCE_JSON STUDIO_TGZ RENDERER_TGZ "
                . "BROWSER_TAR BROWSER_SHA256\n",
        );

        return 2;
    }

    [
        ,
        $studioArgument,
        $evidenceArgument,
        $studioArchiveArgument,
        $rendererArchiveArgument,
        $browserArchiveArgument,
        $browserChecksumArgument,
    ] = $arguments;
    $studioRoot = producerImportDirectory($studioArgument, 'Studio source root');
    $evidencePath = producerImportFile($evidenceArgument, 'publication evidence');
    $studioArchive = producerImportFile($studioArchiveArgument, '@kumwe/studio archive');
    $rendererArchive = producerImportFile($rendererArchiveArgument, 'renderer-web archive');
    $browserArchive = producerImportFile($browserArchiveArgument, 'Studio browser archive');
    $browserChecksum = producerImportFile($browserChecksumArgument, 'Studio browser detached checksum');
    $evidence = producerImportObject(producerImportBytes($evidencePath), $evidencePath);

    $source = $evidence->source ?? null;
    $sourceCommit = $source instanceof stdClass ? ($source->commit ?? null) : null;
    if (
        ($evidence->kind ?? null) !== 'studio-npm-publication-evidence'
        || !is_string($sourceCommit)
        || preg_match('/^[a-f0-9]{40}$/', $sourceCommit) !== 1
        || ($source->repository ?? null) !== 'https://github.com/kumwe/studio'
        || !is_string($source->workflow ?? null)
    ) {
        throw new RuntimeException('Publication evidence has no exact Studio source identity.');
    }
    if (!hash_equals($sourceCommit, producerImportGitCommit($studioRoot))) {
        throw new RuntimeException('Studio source checkout differs from the publication commit.');
    }
    $sourceTree = producerImportGitTree($studioRoot, $sourceCommit);

    $releaseBytes = producerImportGitBlob($studioRoot, $sourceTree, 'studio-release.json');
    $protocolReleaseBytes = producerImportGitBlob(
        $studioRoot,
        $sourceTree,
        'packages/protocol/studio-release.json',
    );
    $testkitReleaseBytes = producerImportGitBlob(
        $studioRoot,
        $sourceTree,
        'packages/testkit/studio-release.json',
    );
    if (
        !hash_equals($releaseBytes, $protocolReleaseBytes)
        || !hash_equals($releaseBytes, $testkitReleaseBytes)
    ) {
        throw new RuntimeException('Studio release-record copies are not byte-identical.');
    }
    $release = producerImportObject($releaseBytes, $studioRoot . '/studio-release.json');
    $releaseVersion = $release->release ?? null;
    if (
        ($release->kind ?? null) !== 'studio-release'
        || !is_string($releaseVersion)
        || ($evidence->release ?? null) !== $releaseVersion
        || !($release->packages ?? null) instanceof stdClass
        || !is_array($release->claimedProfiles ?? null)
    ) {
        throw new RuntimeException('Studio release record and publication evidence disagree.');
    }

    $packageEvidence = producerImportPackageEvidence($evidence, $release);
    $studioArchiveBytes = producerImportVerifyArchive(
        $studioArchive,
        $packageEvidence['@kumwe/studio'],
    );
    $rendererArchiveBytes = producerImportVerifyArchive(
        $rendererArchive,
        $packageEvidence['@kumwe/studio-renderer-web'],
    );

    $assetManifestPackagePath = 'package/dist/browser/studio-assets.json';
    $manifestMembers = producerImportNpmMembers(
        $studioArchiveBytes,
        [$assetManifestPackagePath],
        '@kumwe/studio',
    );
    $assetManifestBytes = $manifestMembers[$assetManifestPackagePath];
    $assetManifest = producerImportObject($assetManifestBytes, '@kumwe/studio:studio-assets.json');
    [$browserAsset, $enhancementAsset, $redistributionAssets] = producerImportBrowserManifest(
        $release,
        $assetManifest,
    );
    $studioPackagePaths = producerImportBrowserPackagePaths($assetManifest);
    $studioMembers = producerImportNpmMembers(
        $studioArchiveBytes,
        $studioPackagePaths,
        '@kumwe/studio',
    );

    $browserPackagePath = 'package/dist/browser/' . $browserAsset->path;
    $enhancementPackagePath = 'package/dist/browser/' . $enhancementAsset->path;
    $browserBytes = $studioMembers[$browserPackagePath];
    $rendererMembers = producerImportNpmMembers(
        $rendererArchiveBytes,
        [$enhancementPackagePath],
        '@kumwe/studio-renderer-web',
    );
    $enhancementBytes = $rendererMembers[$enhancementPackagePath];
    $studioEnhancementBytes = $studioMembers[$enhancementPackagePath];
    if (!hash_equals($enhancementBytes, $studioEnhancementBytes)) {
        throw new RuntimeException('Studio and renderer-web carry different enhancement-runtime bytes.');
    }
    producerImportVerifyAsset($browserAsset, $browserBytes);
    producerImportVerifyAsset($enhancementAsset, $enhancementBytes);

    producerImportVerifyBrowserArchive(
        $browserArchive,
        $browserChecksum,
        $evidence,
        $release,
        $assetManifest,
        $assetManifestBytes,
        $studioMembers,
    );

    $redistributionBytes = [];
    foreach ($redistributionAssets as $asset) {
        $path = (string) $asset->path;
        $bytes = $studioMembers['package/dist/browser/' . $path];
        producerImportVerifyRedistributionAsset($asset, $bytes);
        $redistributionBytes[$path] = $bytes;
    }

    $schemaManifestPath = 'packages/protocol/schemas/manifest.json';
    $corpusManifestPath = 'packages/testkit/corpus-manifest.json';
    $schemaManifestBytes = producerImportGitBlob($studioRoot, $sourceTree, $schemaManifestPath);
    $corpusManifestBytes = producerImportGitBlob($studioRoot, $sourceTree, $corpusManifestPath);
    $schemaManifest = producerImportObject($schemaManifestBytes, $schemaManifestPath);
    $corpusManifest = producerImportObject($corpusManifestBytes, $corpusManifestPath);

    $expected = [
        'studio-release.json' => $releaseBytes,
        'protocol/studio-release.json' => $protocolReleaseBytes,
        'protocol/schemas/manifest.json' => $schemaManifestBytes,
        'testkit/studio-release.json' => $testkitReleaseBytes,
        'testkit/corpus-manifest.json' => $corpusManifestBytes,
        'browser/studio-assets.json' => $assetManifestBytes,
        'browser/' . $browserAsset->path => $browserBytes,
        'browser/' . $enhancementAsset->path => $enhancementBytes,
    ];
    foreach ($redistributionBytes as $path => $bytes) {
        $expected['browser/' . $path] = $bytes;
    }
    $schemaCount = producerImportSchemas($studioRoot, $sourceTree, $schemaManifest, $expected);
    $corpusCount = producerImportCorpus($studioRoot, $sourceTree, $corpusManifest, $expected);

    $corpusDigest = producerImportSri($corpusManifestBytes);
    if (!hash_equals((string) ($release->corpusManifestDigest ?? ''), $corpusDigest)) {
        throw new RuntimeException('Studio release record does not bind its corpus manifest bytes.');
    }

    $pin = producerImportPin(
        $evidence,
        $release,
        $packageEvidence,
        $expected,
        $browserAsset,
        $enhancementAsset,
        $redistributionAssets,
    );
    $expected['PIN.json'] = producerImportJson($pin);
    ksort($expected, SORT_STRING);
    producerImportReplace($expected);

    fwrite(
        STDOUT,
        sprintf(
            "Imported Studio %s at %s: %d schemas, %d corpus files, "
                . "2 verified browser assets, %d redistribution files, "
                . "and 74 verified non-vendored outer-archive members.\n",
            $releaseVersion,
            $sourceCommit,
            $schemaCount,
            $corpusCount,
            count($redistributionAssets),
        ),
    );

    return 0;
}

/** Resolve one ordinary directory while rejecting every symlink component. */
function producerImportDirectory(string $path, string $label): string
{
    $resolved = producerImportCanonicalInputPath($path, $label);
    $identity = lstat($resolved);
    if (!is_array($identity) || ($identity['mode'] & 0170000) !== 0040000) {
        throw new RuntimeException($label . ' is missing, linked, or not a directory.');
    }

    return $resolved;
}

/** Resolve one ordinary file while rejecting every symlink component. */
function producerImportFile(string $path, string $label): string
{
    $resolved = producerImportCanonicalInputPath($path, $label);
    $identity = lstat($resolved);
    if (!is_array($identity) || ($identity['mode'] & 0170000) !== 0100000) {
        throw new RuntimeException($label . ' is missing, linked, or not a file.');
    }

    return $resolved;
}

/** Resolve an input without accepting trailing, final, or ancestor links. */
function producerImportCanonicalInputPath(string $path, string $label): string
{
    if (
        $path === ''
        || str_contains($path, "\0")
        || !mb_check_encoding($path, 'UTF-8')
    ) {
        throw new RuntimeException($label . ' has an invalid input path.');
    }
    $cwd = getcwd();
    if ($cwd === false) {
        throw new RuntimeException('Could not resolve the importer working directory.');
    }
    $absolute = str_starts_with($path, '/') ? $path : $cwd . '/' . $path;
    $segments = explode('/', $absolute);
    $cursor = '';
    foreach ($segments as $index => $segment) {
        if ($index === 0 && $segment === '') {
            continue;
        }
        if ($segment === '' || $segment === '.' || $segment === '..') {
            throw new RuntimeException($label . ' path is not canonical.');
        }
        $cursor .= '/' . $segment;
        $identity = lstat($cursor);
        if (!is_array($identity) || ($identity['mode'] & 0170000) === 0120000) {
            throw new RuntimeException($label . ' path is missing or crosses a symbolic link.');
        }
    }
    $resolved = realpath($absolute);
    if ($resolved === false || $resolved !== $absolute) {
        throw new RuntimeException($label . ' path does not resolve to its canonical identity.');
    }

    return $resolved;
}

/** Read one ordinary file without allocating beyond the fixed byte limit. */
function producerImportBytes(string $path): string
{
    $before = lstat($path);
    if (
        !is_array($before)
        || ($before['mode'] & 0170000) !== 0100000
        || !is_int($before['size'])
        || $before['size'] > PRODUCER_IMPORT_MAX_BYTES
    ) {
        throw new RuntimeException('Import input is unreadable or exceeds its byte bound: ' . $path);
    }
    $handle = fopen($path, 'rb');
    if (!is_resource($handle)) {
        throw new RuntimeException('Import input could not be opened: ' . $path);
    }
    $locked = false;
    try {
        if (!flock($handle, LOCK_SH)) {
            throw new RuntimeException('Import input could not be locked for its bounded read: ' . $path);
        }
        $locked = true;
        $opened = fstat($handle);
        if (
            !is_array($opened)
            || ($opened['mode'] & 0170000) !== 0100000
            || $opened['dev'] !== $before['dev']
            || $opened['ino'] !== $before['ino']
            || $opened['mtime'] !== $before['mtime']
            || $opened['ctime'] !== $before['ctime']
            || !is_int($opened['size'])
            || $opened['size'] > PRODUCER_IMPORT_MAX_BYTES
        ) {
            throw new RuntimeException('Import input identity changed before its bounded read: ' . $path);
        }
        $bytes = '';
        while (!feof($handle)) {
            $remaining = PRODUCER_IMPORT_MAX_BYTES - strlen($bytes) + 1;
            $chunk = fread($handle, min(8192, $remaining));
            if ($chunk === false) {
                throw new RuntimeException('Import input failed during its bounded read: ' . $path);
            }
            $bytes .= $chunk;
            if (strlen($bytes) > PRODUCER_IMPORT_MAX_BYTES) {
                throw new RuntimeException('Import input exceeds its byte bound: ' . $path);
            }
        }
        $after = fstat($handle);
        if (
            !is_array($after)
            || $after['dev'] !== $opened['dev']
            || $after['ino'] !== $opened['ino']
            || $after['size'] !== $opened['size']
            || $after['mtime'] !== $opened['mtime']
            || $after['ctime'] !== $opened['ctime']
            || strlen($bytes) !== $opened['size']
        ) {
            throw new RuntimeException('Import input identity changed during its bounded read: ' . $path);
        }

        return $bytes;
    } finally {
        if ($locked) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
}

/** Decode one JSON object with a source-bearing error. */
function producerImportObject(string $bytes, string $source): stdClass
{
    try {
        $document = json_decode($bytes, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new RuntimeException($source . ' is not valid JSON.', 0, $error);
    }
    if (!$document instanceof stdClass) {
        throw new RuntimeException($source . ' must contain a JSON object.');
    }

    return $document;
}

/** Read the detached source coordinate without accepting an abbreviated SHA. */
function producerImportGitCommit(string $studioRoot): string
{
    $output = producerImportGitCommand($studioRoot, ['rev-parse', '--verify', 'HEAD^{commit}'], 64);
    if (preg_match('/^([a-f0-9]{40})\n$/D', $output, $matches) !== 1) {
        throw new RuntimeException('Could not resolve the Studio source commit.');
    }

    return $matches[1];
}

/**
 * Read the exact commit tree without consulting working-tree files or links.
 *
 * @return array<string, array{mode: string, type: string, object: string}>
 */
function producerImportGitTree(string $studioRoot, string $commit): array
{
    if (preg_match('/^[a-f0-9]{40}$/', $commit) !== 1) {
        throw new RuntimeException('Studio source evidence does not name one full commit object.');
    }
    $output = producerImportGitCommand(
        $studioRoot,
        ['ls-tree', '-rzt', '--full-tree', $commit],
        PRODUCER_IMPORT_MAX_BYTES,
    );
    if ($output === '' || !str_ends_with($output, "\0")) {
        throw new RuntimeException('Studio source commit has no bounded raw tree listing.');
    }

    $records = explode("\0", $output);
    array_pop($records);
    $tree = [];
    foreach ($records as $record) {
        $separator = strpos($record, "\t");
        if ($separator === false) {
            throw new RuntimeException('Studio source commit carries a malformed tree entry.');
        }
        $metadata = substr($record, 0, $separator);
        $path = substr($record, $separator + 1);
        if (
            preg_match(
                '/^([0-7]{6}) (blob|tree|commit) ([a-f0-9]{40})$/D',
                $metadata,
                $matches,
            ) !== 1
            || !producerImportSafeRelative($path)
            || isset($tree[$path])
        ) {
            throw new RuntimeException('Studio source commit carries an unsafe or repeated tree entry.');
        }
        $tree[$path] = [
            'mode' => $matches[1],
            'type' => $matches[2],
            'object' => $matches[3],
        ];
    }

    return $tree;
}

/**
 * Read one bounded ordinary blob directly from the evidence commit object.
 *
 * @param array<string, array{mode: string, type: string, object: string}> $tree
 */
function producerImportGitBlob(
    string $studioRoot,
    array $tree,
    string $path,
    int $maximumBytes = PRODUCER_IMPORT_MAX_BYTES,
): string {
    if ($maximumBytes < 1 || !producerImportSafeRelative($path)) {
        throw new RuntimeException('Studio commit blob request is malformed.');
    }
    $entry = $tree[$path] ?? null;
    if (!is_array($entry)) {
        throw new RuntimeException('Studio source commit is missing required blob ' . $path . '.');
    }
    if ($entry['type'] !== 'blob' || $entry['mode'] !== '100644') {
        throw new RuntimeException('Studio source commit member is not an ordinary data blob: ' . $path);
    }

    $sizeOutput = producerImportGitCommand(
        $studioRoot,
        ['cat-file', '-s', $entry['object']],
        64,
    );
    if (preg_match('/^(0|[1-9][0-9]*)\n$/D', $sizeOutput, $matches) !== 1) {
        throw new RuntimeException('Studio source commit blob has no exact object size: ' . $path);
    }
    $size = filter_var($matches[1], FILTER_VALIDATE_INT);
    if (!is_int($size) || $size > $maximumBytes) {
        throw new RuntimeException('Studio source commit blob exceeds its byte bound: ' . $path);
    }
    $bytes = producerImportGitCommand(
        $studioRoot,
        ['cat-file', 'blob', $entry['object']],
        $maximumBytes,
    );
    if (strlen($bytes) !== $size) {
        throw new RuntimeException('Studio source commit blob differs from its object size: ' . $path);
    }

    return $bytes;
}

/**
 * Run one argument-safe Git plumbing command with bounded output.
 *
 * @param list<string> $arguments Git arguments after the repository root.
 */
function producerImportGitCommand(string $studioRoot, array $arguments, int $maximumStdout): string
{
    if ($arguments === [] || $maximumStdout < 1) {
        throw new RuntimeException('Git plumbing request is malformed.');
    }
    $git = null;
    foreach (['/usr/bin/git', '/usr/local/bin/git'] as $candidate) {
        $identity = lstat($candidate);
        if (
            is_array($identity)
            && ($identity['mode'] & 0170000) === 0100000
            && is_executable($candidate)
        ) {
            $git = $candidate;
            break;
        }
    }
    if (!is_string($git)) {
        throw new RuntimeException('No canonical ordinary Git executable is available.');
    }
    $command = array_merge(
        [$git, '--no-optional-locks', '--no-replace-objects', '-C', $studioRoot],
        $arguments,
    );
    $environment = [
        'GIT_ATTR_NOSYSTEM' => '1',
        'GIT_CONFIG_GLOBAL' => '/dev/null',
        'GIT_CONFIG_NOSYSTEM' => '1',
        'GIT_CONFIG_SYSTEM' => '/dev/null',
        'GIT_NO_REPLACE_OBJECTS' => '1',
        'GIT_OPTIONAL_LOCKS' => '0',
        'LANG' => 'C',
        'LC_ALL' => 'C',
        'PATH' => '/usr/local/bin:/usr/bin:/bin',
    ];
    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        null,
        $environment,
        ['bypass_shell' => true],
    );
    if (!is_resource($process) || count($pipes) !== 3) {
        throw new RuntimeException('Could not start bounded Git plumbing.');
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $streams = [1 => $pipes[1], 2 => $pipes[2]];
    $failure = null;
    $deadline = microtime(true) + 30.0;
    try {
        while ($streams !== []) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Bounded Git plumbing exceeded its time limit.');
            }
            $read = array_values($streams);
            $write = null;
            $except = null;
            $selected = stream_select($read, $write, $except, 1, 0);
            if ($selected === false) {
                throw new RuntimeException('Could not read bounded Git plumbing output.');
            }
            foreach ($read as $stream) {
                $chunk = fread($stream, 8192);
                if ($chunk === false) {
                    throw new RuntimeException('Could not read bounded Git plumbing bytes.');
                }
                $index = $stream === $pipes[1] ? 1 : 2;
                if ($index === 1) {
                    $stdout .= $chunk;
                    if (strlen($stdout) > $maximumStdout) {
                        throw new RuntimeException('Git plumbing stdout exceeds its byte bound.');
                    }
                } else {
                    $stderr .= $chunk;
                    if (strlen($stderr) > 65536) {
                        throw new RuntimeException('Git plumbing stderr exceeds its byte bound.');
                    }
                }
                if (feof($stream)) {
                    unset($streams[$index]);
                }
            }
        }
    } catch (Throwable $error) {
        $failure = $error;
        proc_terminate($process);
    }
    foreach ([1, 2] as $index) {
        if (is_resource($pipes[$index])) {
            fclose($pipes[$index]);
        }
    }
    $status = proc_close($process);
    if ($failure instanceof Throwable) {
        throw $failure;
    }
    if ($status !== 0) {
        $detail = trim($stderr);
        throw new RuntimeException(
            'Git plumbing failed for ' . $arguments[0] . ($detail === '' ? '.' : ': ' . $detail),
        );
    }

    return $stdout;
}

/**
 * Validate the eight provenance-backed package records against the release.
 *
 * @return array<string, stdClass>
 */
function producerImportPackageEvidence(stdClass $evidence, stdClass $release): array
{
    $entries = $evidence->packages ?? null;
    if (!is_array($entries) || !array_is_list($entries)) {
        throw new RuntimeException('Publication evidence has no ordered package list.');
    }
    $versions = get_object_vars($release->packages);
    $packages = [];
    foreach ($entries as $entry) {
        if (!$entry instanceof stdClass) {
            throw new RuntimeException('Publication evidence carries a malformed package entry.');
        }
        $name = $entry->name ?? null;
        if (
            !is_string($name)
            || !isset($versions[$name])
            || isset($packages[$name])
            || ($entry->version ?? null) !== $versions[$name]
            || !is_string($entry->tarball ?? null)
            || !is_string($entry->attestation ?? null)
            || !is_int($entry->bytes ?? null)
            || $entry->bytes < 1
            || preg_match('/^[a-f0-9]{64}$/', (string) ($entry->sha256 ?? '')) !== 1
            || preg_match('/^[a-f0-9]{40}$/', (string) ($entry->shasum ?? '')) !== 1
            || preg_match('/^sha512-[A-Za-z0-9+\/]+={0,2}$/', (string) ($entry->integrity ?? '')) !== 1
        ) {
            throw new RuntimeException('Publication evidence carries an invalid package coordinate.');
        }
        $packages[$name] = $entry;
    }
    ksort($packages, SORT_STRING);
    ksort($versions, SORT_STRING);
    if (array_keys($packages) !== array_keys($versions) || count($packages) !== 8) {
        throw new RuntimeException('Publication evidence must bind exactly the coordinated eight-package family.');
    }

    return $packages;
}

/** Prove and return one bounded npm tarball against its public registry record. */
function producerImportVerifyArchive(string $path, stdClass $evidence): string
{
    $bytes = producerImportBytes($path);
    $integrity = 'sha512-' . base64_encode(hash('sha512', $bytes, true));
    if (
        strlen($bytes) !== $evidence->bytes
        || !hash_equals($evidence->sha256, hash('sha256', $bytes))
        || !hash_equals($evidence->shasum, sha1($bytes))
        || !hash_equals($evidence->integrity, $integrity)
    ) {
        throw new RuntimeException($evidence->name . ' tarball differs from its publication evidence.');
    }

    return $bytes;
}

/**
 * Resolve the exact npm browser members declared by studio-assets.json.
 *
 * @return list<string>
 */
function producerImportBrowserPackagePaths(stdClass $manifest): array
{
    $assets = $manifest->assets ?? null;
    if (!is_array($assets) || !array_is_list($assets) || count($assets) !== 73) {
        throw new RuntimeException('Browser manifest must declare exactly 73 npm browser members.');
    }
    $prefix = 'package/dist/browser/';
    $paths = [$prefix . 'studio-assets.json'];
    $seen = ['studio-assets.json' => true];
    foreach ($assets as $asset) {
        $path = $asset instanceof stdClass ? ($asset->path ?? null) : null;
        if (!is_string($path) || !producerImportSafeRelative($path) || isset($seen[$path])) {
            throw new RuntimeException('Browser manifest repeats or malforms an npm browser member path.');
        }
        $seen[$path] = true;
        $paths[] = $prefix . $path;
    }

    return $paths;
}

/**
 * Prove the exact non-vendored Studio browser tar and detached checksum.
 *
 * Every outer member must be one manifest member from the verified
 * @kumwe/studio package. The enhancement runtime has already been proved
 * byte-identical to @kumwe/studio-renderer-web before this check runs.
 *
 * @param array<string, string> $studioPackage
 */
function producerImportVerifyBrowserArchive(
    string $archivePath,
    string $checksumPath,
    stdClass $evidence,
    stdClass $release,
    stdClass $manifest,
    string $manifestBytes,
    array $studioPackage,
): void {
    $browserEvidence = $evidence->browserArchive ?? null;
    $candidate = $browserEvidence instanceof stdClass ? ($browserEvidence->candidate ?? null) : null;
    $archive = $candidate instanceof stdClass ? ($candidate->archive ?? null) : null;
    $checksum = $candidate instanceof stdClass ? ($candidate->checksum ?? null) : null;
    $publication = $browserEvidence instanceof stdClass ? ($browserEvidence->publication ?? null) : null;
    $releaseVersion = $release->release ?? null;
    $archiveStem = is_string($releaseVersion) ? 'studio-browser-' . $releaseVersion : '';
    $archiveFile = $archiveStem . '-' . substr(PRODUCER_IMPORT_BROWSER_ARCHIVE_SHA256, 0, 16) . '.tar';
    $checksumFile = $archiveFile . '.sha256';
    $tag = is_string($releaseVersion) ? 'studio-v' . $releaseVersion : '';
    $releaseUrl = 'https://github.com/kumwe/studio/releases/tag/' . $tag;
    $downloadRoot = 'https://github.com/kumwe/studio/releases/download/' . $tag . '/';
    $integrity = 'sha512-' . base64_encode((string) hex2bin(PRODUCER_IMPORT_BROWSER_ARCHIVE_SHA512));

    if (
        !$browserEvidence instanceof stdClass
        || producerImportSortedMembers($browserEvidence) !== [
            'archiveStem',
            'candidate',
            'publication',
            'reason',
            'status',
        ]
        || !$candidate instanceof stdClass
        || producerImportSortedMembers($candidate) !== ['archive', 'checksum']
        || !$archive instanceof stdClass
        || producerImportSortedMembers($archive) !== [
            'budgetBytes',
            'bytes',
            'file',
            'integrity',
            'manifestSha256',
            'members',
            'sha256',
            'sha512',
        ]
        || !$checksum instanceof stdClass
        || producerImportSortedMembers($checksum) !== ['bytes', 'file', 'sha256']
        || !$publication instanceof stdClass
        || producerImportSortedMembers($publication) !== [
            'expectedArchiveUrl',
            'expectedChecksumUrl',
            'expectedReleaseUrl',
            'expectedTag',
            'status',
        ]
        || ($browserEvidence->archiveStem ?? null) !== $archiveStem
        || ($browserEvidence->status ?? null) !== 'verified-local-candidate'
        || !is_string($browserEvidence->reason ?? null)
        || $browserEvidence->reason === ''
        || ($archive->file ?? null) !== $archiveFile
        || ($archive->bytes ?? null) !== PRODUCER_IMPORT_BROWSER_ARCHIVE_BYTES
        || ($archive->budgetBytes ?? null) !== PRODUCER_IMPORT_BROWSER_ARCHIVE_BUDGET_BYTES
        || ($archive->sha256 ?? null) !== PRODUCER_IMPORT_BROWSER_ARCHIVE_SHA256
        || ($archive->sha512 ?? null) !== PRODUCER_IMPORT_BROWSER_ARCHIVE_SHA512
        || ($archive->integrity ?? null) !== $integrity
        || ($archive->manifestSha256 ?? null) !== PRODUCER_IMPORT_BROWSER_MANIFEST_SHA256
        || ($archive->members ?? null) !== PRODUCER_IMPORT_BROWSER_ARCHIVE_MEMBERS
        || ($checksum->file ?? null) !== $checksumFile
        || ($checksum->bytes ?? null) !== PRODUCER_IMPORT_BROWSER_CHECKSUM_BYTES
        || ($checksum->sha256 ?? null) !== PRODUCER_IMPORT_BROWSER_CHECKSUM_SHA256
        || ($publication->status ?? null) !== 'unavailable'
        || ($publication->expectedTag ?? null) !== $tag
        || ($publication->expectedReleaseUrl ?? null) !== $releaseUrl
        || ($publication->expectedArchiveUrl ?? null) !== $downloadRoot . $archiveFile
        || ($publication->expectedChecksumUrl ?? null) !== $downloadRoot . $checksumFile
    ) {
        throw new RuntimeException('Publication evidence has no exact blocked browser-archive candidate.');
    }
    if (basename($archivePath) !== $archiveFile || basename($checksumPath) !== $checksumFile) {
        throw new RuntimeException('Browser archive inputs do not use their content-derived release names.');
    }

    $archiveBytes = producerImportBytes($archivePath);
    $checksumBytes = producerImportBytes($checksumPath);
    if (
        strlen($archiveBytes) !== PRODUCER_IMPORT_BROWSER_ARCHIVE_BYTES
        || strlen($archiveBytes) > PRODUCER_IMPORT_BROWSER_ARCHIVE_BUDGET_BYTES
        || !hash_equals(PRODUCER_IMPORT_BROWSER_ARCHIVE_SHA256, hash('sha256', $archiveBytes))
        || !hash_equals(PRODUCER_IMPORT_BROWSER_ARCHIVE_SHA512, hash('sha512', $archiveBytes))
        || !hash_equals($integrity, 'sha512-' . base64_encode(hash('sha512', $archiveBytes, true)))
        || strlen($checksumBytes) !== PRODUCER_IMPORT_BROWSER_CHECKSUM_BYTES
        || !hash_equals(PRODUCER_IMPORT_BROWSER_CHECKSUM_SHA256, hash('sha256', $checksumBytes))
        || !hash_equals(
            PRODUCER_IMPORT_BROWSER_ARCHIVE_SHA256 . '  ' . $archiveFile . "\n",
            $checksumBytes,
        )
        || !hash_equals(PRODUCER_IMPORT_BROWSER_MANIFEST_SHA256, hash('sha256', $manifestBytes))
    ) {
        throw new RuntimeException('Browser archive or detached checksum differs from the reviewed candidate.');
    }

    $outerMembers = producerImportUstarMembers($archiveBytes, $archiveStem);
    $expectedMembers = ['studio-assets.json' => $manifestBytes];
    $assets = $manifest->assets ?? null;
    if (!is_array($assets) || !array_is_list($assets) || count($assets) !== 73) {
        throw new RuntimeException('Browser manifest must declare the exact 73-member asset closure.');
    }
    $roleCounts = [];
    foreach ($assets as $asset) {
        $path = $asset instanceof stdClass ? ($asset->path ?? null) : null;
        $role = $asset instanceof stdClass ? ($asset->role ?? null) : null;
        if (
            !is_string($path)
            || !producerImportSafeRelative($path)
            || isset($expectedMembers[$path])
            || !is_string($role)
            || !is_int($asset->bytes ?? null)
            || $asset->bytes < 1
            || !is_string($asset->mediaType ?? null)
            || !is_string($asset->integrity ?? null)
        ) {
            throw new RuntimeException('Browser manifest carries a malformed outer-archive member.');
        }
        $packagePath = 'package/dist/browser/' . $path;
        $packageBytes = $studioPackage[$packagePath] ?? null;
        if (
            !is_string($packageBytes)
            || strlen($packageBytes) !== $asset->bytes
            || !hash_equals(producerImportSri($packageBytes), $asset->integrity)
        ) {
            throw new RuntimeException('Browser manifest differs from @kumwe/studio bytes: ' . $path);
        }
        $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
        $expectedMembers[$path] = $packageBytes;
    }
    ksort($roleCounts, SORT_STRING);
    if (
        $roleCounts !== [
            'browser-module' => 1,
            'documentation' => 32,
            'enhancement-runtime' => 1,
            'license' => 13,
            'notice' => 1,
            'release-record' => 1,
            'schema' => 24,
        ]
    ) {
        throw new RuntimeException('Browser manifest differs from the reviewed 73-asset role closure.');
    }

    ksort($outerMembers, SORT_STRING);
    ksort($expectedMembers, SORT_STRING);
    if (array_keys($outerMembers) !== array_keys($expectedMembers)) {
        throw new RuntimeException('Browser archive member set differs from studio-assets.json.');
    }
    foreach ($expectedMembers as $path => $bytes) {
        if (!hash_equals($bytes, $outerMembers[$path])) {
            throw new RuntimeException('Browser archive member differs from @kumwe/studio: ' . $path);
        }
    }
}

/**
 * Parse the deterministic browser archive as strict regular-file ustar.
 *
 * @return array<string, string> Archive-relative member bytes by path.
 */
function producerImportUstarMembers(string $archive, string $requiredPrefix): array
{
    $length = strlen($archive);
    if ($length < 1024 || $length % 512 !== 0 || !producerImportSafeRelative($requiredPrefix)) {
        throw new RuntimeException('Browser archive has an invalid ustar envelope.');
    }

    $members = [];
    $zeroBlock = str_repeat("\0", 512);
    for ($offset = 0; $offset + 512 <= $length;) {
        $header = substr($archive, $offset, 512);
        if (hash_equals($zeroBlock, $header)) {
            if (
                $offset + 1024 !== $length
                || !hash_equals($zeroBlock, substr($archive, $offset + 512, 512))
            ) {
                throw new RuntimeException('Browser archive must end with exactly two zero blocks.');
            }
            if (count($members) !== PRODUCER_IMPORT_BROWSER_ARCHIVE_MEMBERS) {
                throw new RuntimeException('Browser archive has the wrong regular-member count.');
            }

            return $members;
        }

        $checksumField = substr($header, 148, 8);
        if (preg_match('/^[0-7]{6}\x00 $/D', $checksumField) !== 1) {
            throw new RuntimeException('Browser archive has a malformed ustar header checksum.');
        }
        $storedChecksum = intval(substr($checksumField, 0, 6), 8);
        $checksumHeader = substr_replace($header, str_repeat(' ', 8), 148, 8);
        $octets = unpack('C*', $checksumHeader);
        if (!is_array($octets) || array_sum($octets) !== $storedChecksum) {
            throw new RuntimeException('Browser archive has a mismatched ustar header checksum.');
        }
        if (
            substr($header, 100, 8) !== "0000644\0"
            || substr($header, 108, 8) !== "0000000\0"
            || substr($header, 116, 8) !== "0000000\0"
            || substr($header, 136, 12) !== "00000000000\0"
            || $header[156] !== '0'
            || substr($header, 157, 100) !== str_repeat("\0", 100)
            || substr($header, 257, 6) !== "ustar\0"
            || substr($header, 263, 2) !== '00'
            || producerImportTarText(substr($header, 265, 32), 'owner') !== 'root'
            || producerImportTarText(substr($header, 297, 32), 'group') !== 'root'
            || substr($header, 329, 16) !== str_repeat("\0", 16)
            || substr($header, 500, 12) !== str_repeat("\0", 12)
        ) {
            throw new RuntimeException('Browser archive contains a non-deterministic or non-regular ustar header.');
        }

        $sizeField = substr($header, 124, 12);
        if (preg_match('/^[0-7]{11}\x00$/D', $sizeField) !== 1) {
            throw new RuntimeException('Browser archive carries an invalid ustar member size.');
        }
        $size = intval(substr($sizeField, 0, 11), 8);
        $name = producerImportTarText(substr($header, 0, 100), 'name');
        $prefix = producerImportTarText(substr($header, 345, 155), 'prefix');
        $path = $prefix === '' ? $name : $prefix . '/' . $name;
        $requiredStart = $requiredPrefix . '/';
        $relative = str_starts_with($path, $requiredStart)
            ? substr($path, strlen($requiredStart))
            : '';
        if (
            $name === ''
            || !producerImportSafeRelative($path)
            || !producerImportSafeRelative($relative)
            || isset($members[$relative])
        ) {
            throw new RuntimeException('Browser archive carries an unsafe or repeated ustar path.');
        }

        $contentStart = $offset + 512;
        $contentEnd = $contentStart + $size;
        $paddedEnd = $contentStart + intdiv($size + 511, 512) * 512;
        if ($contentEnd > $length || $paddedEnd > $length) {
            throw new RuntimeException('Browser archive member exceeds its ustar boundary.');
        }
        $padding = substr($archive, $contentEnd, $paddedEnd - $contentEnd);
        if ($padding !== '' && !hash_equals(str_repeat("\0", strlen($padding)), $padding)) {
            throw new RuntimeException('Browser archive contains non-zero ustar member padding.');
        }
        $members[$relative] = substr($archive, $contentStart, $size);
        $offset = $paddedEnd;
    }

    throw new RuntimeException('Browser archive has no exact two-block ustar terminator.');
}

/** Decode one zero-padded UTF-8 ustar text field. */
function producerImportTarText(string $field, string $label): string
{
    $terminator = strpos($field, "\0");
    if ($terminator === false) {
        $text = $field;
    } else {
        $text = substr($field, 0, $terminator);
        if (substr($field, $terminator) !== str_repeat("\0", strlen($field) - $terminator)) {
            throw new RuntimeException('Browser archive has malformed ustar ' . $label . ' padding.');
        }
    }
    if (!mb_check_encoding($text, 'UTF-8')) {
        throw new RuntimeException('Browser archive has non-UTF-8 ustar ' . $label . '.');
    }

    return $text;
}

/** @return list<string> */
function producerImportSortedMembers(stdClass $object): array
{
    $members = array_map('strval', array_keys(get_object_vars($object)));
    sort($members, SORT_STRING);

    return $members;
}

/**
 * Stream one verified npm gzip/tar and retain only exact required members.
 *
 * The parser admits only ordinary type-0 ustar files. Links, directories,
 * devices, PAX/GNU extensions and every other special member fail closed.
 * Declared member size, member count and the total inflated envelope are
 * bounded before retained content is accumulated.
 *
 * @param list<string> $requiredPaths
 *
 * @return array<string, string>
 */
function producerImportNpmMembers(string $archive, array $requiredPaths, string $package): array
{
    if ($archive === '' || strlen($archive) > PRODUCER_IMPORT_MAX_BYTES || $requiredPaths === []) {
        throw new RuntimeException($package . ' npm archive input is malformed.');
    }
    $required = [];
    foreach ($requiredPaths as $path) {
        if (
            !producerImportSafeRelative($path)
            || !str_starts_with($path, 'package/')
            || isset($required[$path])
        ) {
            throw new RuntimeException($package . ' npm member request is unsafe or repeated.');
        }
        $required[$path] = true;
    }

    $stream = fopen('php://temp/maxmemory:' . PRODUCER_IMPORT_MAX_BYTES, 'w+b');
    if (!is_resource($stream)) {
        throw new RuntimeException('Could not create the bounded npm archive stream.');
    }
    try {
        $offset = 0;
        while ($offset < strlen($archive)) {
            $written = fwrite($stream, substr($archive, $offset, 8192));
            if (!is_int($written) || $written < 1) {
                throw new RuntimeException('Could not stage the bounded npm archive bytes.');
            }
            $offset += $written;
        }
        if (!rewind($stream)) {
            throw new RuntimeException('Could not rewind the bounded npm archive stream.');
        }
        $filter = stream_filter_append(
            $stream,
            'zlib.inflate',
            STREAM_FILTER_READ,
            ['window' => 31],
        );
        if (!is_resource($filter)) {
            throw new RuntimeException('Could not create the bounded gzip inflater.');
        }

        $total = 0;
        $memberCount = 0;
        $seen = [];
        $found = [];
        $zeroBlock = str_repeat("\0", 512);
        while (true) {
            $header = producerImportInflatedExact($stream, 512, $total, $package);
            if (hash_equals($zeroBlock, $header)) {
                $second = producerImportInflatedExact($stream, 512, $total, $package);
                if (!hash_equals($zeroBlock, $second)) {
                    throw new RuntimeException($package . ' npm tar has only one zero terminator block.');
                }
                producerImportInflatedZeroTail($stream, $total, $package);
                break;
            }

            [$path, $size] = producerImportNpmTarHeader($header, $package);
            $memberCount++;
            if (
                $memberCount > PRODUCER_IMPORT_NPM_MAX_MEMBERS
                || isset($seen[$path])
                || $size > PRODUCER_IMPORT_MAX_BYTES
            ) {
                throw new RuntimeException($package . ' npm tar exceeds its member bounds or repeats a path.');
            }
            $seen[$path] = true;
            $retain = isset($required[$path]);
            $bytes = producerImportInflatedMember($stream, $size, $total, $package, $retain);
            if ($retain) {
                $found[$path] = $bytes;
            }
            $paddingBytes = (512 - ($size % 512)) % 512;
            if ($paddingBytes > 0) {
                $padding = producerImportInflatedExact($stream, $paddingBytes, $total, $package);
                if (!hash_equals(str_repeat("\0", $paddingBytes), $padding)) {
                    throw new RuntimeException($package . ' npm tar contains non-zero member padding.');
                }
            }
        }
        foreach (array_keys($required) as $path) {
            if (!array_key_exists($path, $found)) {
                throw new RuntimeException($package . ' is missing required ordinary npm member ' . $path . '.');
            }
        }

        return $found;
    } finally {
        fclose($stream);
    }
}

/**
 * Parse one exact ordinary type-0 ustar header from an npm tar.
 *
 * @return array{0: string, 1: int}
 */
function producerImportNpmTarHeader(string $header, string $package): array
{
    if (strlen($header) !== 512) {
        throw new RuntimeException($package . ' npm tar header is truncated.');
    }
    $checksumField = substr($header, 148, 8);
    if (preg_match('/^[0-7]{6} \x00$/D', $checksumField) !== 1) {
        throw new RuntimeException($package . ' npm tar header checksum is malformed.');
    }
    $checksumHeader = substr_replace($header, str_repeat(' ', 8), 148, 8);
    $octets = unpack('C*', $checksumHeader);
    if (
        !is_array($octets)
        || array_sum($octets) !== intval(substr($checksumField, 0, 6), 8)
        || $header[156] !== '0'
        || substr($header, 157, 100) !== str_repeat("\0", 100)
        || substr($header, 257, 6) !== "ustar\0"
        || substr($header, 263, 2) !== '00'
    ) {
        throw new RuntimeException($package . ' npm tar contains a link, special member, or invalid ustar header.');
    }
    $sizeField = substr($header, 124, 12);
    if (preg_match('/^[0-7]{10} \x00$/D', $sizeField) !== 1) {
        throw new RuntimeException($package . ' npm tar member size is malformed.');
    }
    $size = intval(substr($sizeField, 0, 10), 8);
    $name = producerImportTarText(substr($header, 0, 100), 'name');
    $prefix = producerImportTarText(substr($header, 345, 155), 'prefix');
    $path = $prefix === '' ? $name : $prefix . '/' . $name;
    if (!producerImportSafeRelative($path) || !str_starts_with($path, 'package/')) {
        throw new RuntimeException($package . ' npm tar carries an unsafe package path.');
    }

    return [$path, $size];
}

/** Read one exact bounded amount of inflated tar data. */
function producerImportInflatedExact($stream, int $length, int &$total, string $package): string
{
    if (
        $length < 0
        || $total > PRODUCER_IMPORT_NPM_UNCOMPRESSED_MAX_BYTES - $length
    ) {
        throw new RuntimeException($package . ' inflated npm tar exceeds its total byte bound.');
    }
    $bytes = '';
    while (strlen($bytes) < $length) {
        $chunk = fread($stream, min(8192, $length - strlen($bytes)));
        if ($chunk === false || $chunk === '') {
            throw new RuntimeException($package . ' inflated npm tar ended before its declared boundary.');
        }
        $bytes .= $chunk;
    }
    $total += $length;

    return $bytes;
}

/** Read or discard one size-preflighted regular npm member. */
function producerImportInflatedMember(
    $stream,
    int $length,
    int &$total,
    string $package,
    bool $retain,
): string {
    if (
        $length < 0
        || $length > PRODUCER_IMPORT_MAX_BYTES
        || $total > PRODUCER_IMPORT_NPM_UNCOMPRESSED_MAX_BYTES - $length
    ) {
        throw new RuntimeException($package . ' npm member exceeds its declared byte bounds.');
    }
    $remaining = $length;
    $bytes = '';
    while ($remaining > 0) {
        $chunk = fread($stream, min(8192, $remaining));
        if ($chunk === false || $chunk === '') {
            throw new RuntimeException($package . ' npm member ended before its declared size.');
        }
        $remaining -= strlen($chunk);
        if ($retain) {
            $bytes .= $chunk;
        }
    }
    $total += $length;

    return $bytes;
}

/** Drain only bounded zero padding after the two required tar terminators. */
function producerImportInflatedZeroTail($stream, int &$total, string $package): void
{
    while (!feof($stream)) {
        $remaining = PRODUCER_IMPORT_NPM_UNCOMPRESSED_MAX_BYTES - $total + 1;
        $chunk = fread($stream, min(8192, $remaining));
        if ($chunk === false) {
            throw new RuntimeException($package . ' npm tar failed while draining its zero tail.');
        }
        if ($chunk === '') {
            if (feof($stream)) {
                break;
            }
            throw new RuntimeException($package . ' npm tar stalled before its gzip boundary.');
        }
        $total += strlen($chunk);
        if (
            $total > PRODUCER_IMPORT_NPM_UNCOMPRESSED_MAX_BYTES
            || !hash_equals(str_repeat("\0", strlen($chunk)), $chunk)
        ) {
            throw new RuntimeException($package . ' npm tar has an oversized or non-zero trailing envelope.');
        }
    }
}

/**
 * Resolve exactly one browser module and enhancement runtime from the manifest.
 *
 * @return array{0: stdClass, 1: stdClass, 2: list<stdClass>}
 */
function producerImportBrowserManifest(stdClass $release, stdClass $manifest): array
{
    $locators = $release->browserArtifacts ?? null;
    $releaseIdentity = $manifest->release ?? null;
    if (
        !$locators instanceof stdClass
        || ($locators->manifest->name ?? null) !== 'studio-assets.json'
        || ($locators->manifest->schema ?? null)
            !== 'https://schemas.kumwe.org/studio/v1/studio-browser-assets.schema.json'
        || ($manifest->kind ?? null) !== 'studio-browser-assets'
        || ($manifest->schemaVersion ?? null) !== 1
        || !$releaseIdentity instanceof stdClass
        || ($releaseIdentity->version ?? null) !== ($release->release ?? null)
        || ($releaseIdentity->corpusManifestDigest ?? null) !== ($release->corpusManifestDigest ?? null)
        || !is_array($manifest->assets ?? null)
        || !array_is_list($manifest->assets)
    ) {
        throw new RuntimeException('Browser manifest does not identify the coordinated Studio release.');
    }

    $byRole = [];
    foreach ($manifest->assets as $asset) {
        $role = $asset instanceof stdClass ? ($asset->role ?? null) : null;
        if (!is_string($role)) {
            throw new RuntimeException('Browser manifest carries an asset without a role.');
        }
        $byRole[$role][] = $asset;
    }
    if (
        count($byRole['browser-module'] ?? []) !== 1
        || count($byRole['enhancement-runtime'] ?? []) !== 1
    ) {
        throw new RuntimeException('Browser manifest must resolve exactly one required asset for each runtime role.');
    }
    $browser = $byRole['browser-module'][0];
    $enhancement = $byRole['enhancement-runtime'][0];
    if (
        ($locators->authoringArchive->assetRole ?? null) !== 'browser-module'
        || ($locators->authoringArchive->loading ?? null) !== 'module'
        || ($locators->authoringArchive->archiveStem ?? null)
            !== 'studio-browser-' . $release->release
        || ($locators->enhancementRuntime->assetRole ?? null) !== 'enhancement-runtime'
        || ($locators->enhancementRuntime->loading ?? null) !== 'defer'
        || ($locators->enhancementRuntime->package ?? null) !== '@kumwe/studio-renderer-web'
        || ($locators->enhancementRuntime->packageBasePath ?? null) !== 'dist/browser/'
        || ($manifest->module->entryPoint ?? null) !== ($browser->path ?? null)
        || ($manifest->enhancementRuntime->entryPoint ?? null) !== ($enhancement->path ?? null)
    ) {
        throw new RuntimeException('Browser artifact locators differ from their manifest entries.');
    }

    $redistribution = [];
    $redistributionPaths = [];
    $roleCounts = ['license' => 0, 'notice' => 0];
    foreach ($manifest->assets as $asset) {
        $role = $asset instanceof stdClass ? ($asset->role ?? null) : null;
        if (!in_array($role, ['license', 'notice'], true)) {
            continue;
        }
        $path = $asset instanceof stdClass ? ($asset->path ?? null) : null;
        if (!is_string($path) || isset($redistributionPaths[$path])) {
            throw new RuntimeException('Browser manifest repeats or malforms a redistribution path.');
        }
        $redistributionPaths[$path] = true;
        $roleCounts[$role]++;
        $redistribution[] = $asset;
    }
    if (
        count($redistribution) !== 14
        || $roleCounts !== ['license' => 13, 'notice' => 1]
    ) {
        throw new RuntimeException(
            'Studio beta.2 browser manifest must declare its complete 14-file redistribution closure.',
        );
    }

    return [$browser, $enhancement, $redistribution];
}

/** Prove selected browser bytes against every authoritative manifest field. */
function producerImportVerifyAsset(stdClass $asset, string $bytes): void
{
    $sha256 = hash('sha256', $bytes);
    $integrity = 'sha256-' . base64_encode(hash('sha256', $bytes, true));
    if (
        !is_string($asset->path ?? null)
        || !is_string($asset->role ?? null)
        || !is_int($asset->bytes ?? null)
        || !is_int($asset->budgetBytes ?? null)
        || ($asset->minified ?? null) !== true
        || $asset->bytes !== strlen($bytes)
        || $asset->bytes > $asset->budgetBytes
        || !hash_equals((string) ($asset->contentHash ?? ''), $sha256)
        || !hash_equals((string) ($asset->integrity ?? ''), $integrity)
        || !str_contains($asset->path, substr($sha256, 0, 16))
    ) {
        throw new RuntimeException('Browser asset bytes differ from studio-assets.json.');
    }
}

/** Prove one manifest-declared notice/license member from @kumwe/studio. */
function producerImportVerifyRedistributionAsset(stdClass $asset, string $bytes): void
{
    $members = array_map('strval', array_keys(get_object_vars($asset)));
    sort($members, SORT_STRING);
    $role = $asset->role ?? null;
    $path = $asset->path ?? null;
    $mediaType = $asset->mediaType ?? null;
    $integrity = 'sha256-' . base64_encode(hash('sha256', $bytes, true));
    if (
        $members !== ['bytes', 'integrity', 'mediaType', 'path', 'role']
        || !in_array($role, ['license', 'notice'], true)
        || !is_string($path)
        || !producerImportSafeRelative($path)
        || !is_int($asset->bytes ?? null)
        || $asset->bytes < 1
        || $asset->bytes !== strlen($bytes)
        || !is_string($asset->integrity ?? null)
        || !hash_equals($asset->integrity, $integrity)
        || ($role === 'notice' && ($path !== 'THIRD_PARTY_NOTICES.md' || $mediaType !== 'text/markdown'))
        || (
            $role === 'license'
            && $mediaType !== 'text/plain'
        )
        || (
            $role === 'license'
            && $path !== 'LICENSE'
            && preg_match('#^third-party-licenses/[^/]+\.txt$#', $path) !== 1
        )
    ) {
        throw new RuntimeException('Redistribution bytes differ from studio-assets.json: ' . (string) $path);
    }
}

/**
 * Add every schema-manifest member to the output map.
 *
 * @param array<string, array{mode: string, type: string, object: string}> $sourceTree
 * @param array<string, string> $expected
 */
function producerImportSchemas(
    string $studioRoot,
    array $sourceTree,
    stdClass $manifest,
    array &$expected,
): int {
    if (($manifest->kind ?? null) !== 'schema-manifest' || !is_array($manifest->schemas ?? null)) {
        throw new RuntimeException('Studio schema manifest is malformed.');
    }
    $seen = [];
    foreach ($manifest->schemas as $entry) {
        $file = $entry instanceof stdClass ? ($entry->file ?? null) : null;
        $digest = $entry instanceof stdClass ? ($entry->digest ?? null) : null;
        if (!is_string($file) || !producerImportSafeRelative($file) || isset($seen[$file])) {
            throw new RuntimeException('Studio schema manifest carries a malformed or repeated path.');
        }
        $bytes = producerImportGitBlob(
            $studioRoot,
            $sourceTree,
            'packages/protocol/schemas/' . $file,
        );
        if (!is_string($digest) || !hash_equals($digest, producerImportSri($bytes))) {
            throw new RuntimeException('Studio schema digest mismatch: ' . $file);
        }
        $seen[$file] = true;
        $expected['protocol/schemas/' . $file] = $bytes;
    }

    return count($seen);
}

/**
 * Add every corpus-manifest member to the output map.
 *
 * @param array<string, array{mode: string, type: string, object: string}> $sourceTree
 * @param array<string, string> $expected
 */
function producerImportCorpus(
    string $studioRoot,
    array $sourceTree,
    stdClass $manifest,
    array &$expected,
): int {
    if (!is_array($manifest->groups ?? null) || !array_is_list($manifest->groups)) {
        throw new RuntimeException('Studio corpus manifest is malformed.');
    }
    $seen = [];
    foreach ($manifest->groups as $group) {
        $path = $group instanceof stdClass ? ($group->path ?? null) : null;
        $files = $group instanceof stdClass ? ($group->files ?? null) : null;
        if (!is_string($path) || !producerImportSafeRelative($path) || !is_array($files)) {
            throw new RuntimeException('Studio corpus manifest carries a malformed group.');
        }
        foreach ($files as $entry) {
            $file = $entry instanceof stdClass ? ($entry->file ?? null) : null;
            $digest = $entry instanceof stdClass ? ($entry->digest ?? null) : null;
            $relative = is_string($file) ? $path . '/' . $file : '';
            if (!is_string($file) || !producerImportSafeRelative($file) || isset($seen[$relative])) {
                throw new RuntimeException('Studio corpus manifest carries a malformed or repeated path.');
            }
            $bytes = producerImportGitBlob(
                $studioRoot,
                $sourceTree,
                'packages/testkit/' . $relative,
            );
            if (!is_string($digest) || !hash_equals($digest, producerImportSri($bytes))) {
                throw new RuntimeException('Studio corpus digest mismatch: ' . $relative);
            }
            $seen[$relative] = true;
            $expected['testkit/' . $relative] = $bytes;
        }
    }

    return count($seen);
}

/**
 * Construct the shipped provenance and direct-file pin.
 *
 * @param array<string, stdClass> $packageEvidence
 * @param array<string, string>   $expected
 * @param list<stdClass>          $redistributionAssets
 *
 * @return array<string, mixed>
 */
function producerImportPin(
    stdClass $evidence,
    stdClass $release,
    array $packageEvidence,
    array $expected,
    stdClass $browserAsset,
    stdClass $enhancementAsset,
    array $redistributionAssets,
): array {
    $directPaths = [
        'studio-release.json',
        'protocol/studio-release.json',
        'protocol/schemas/manifest.json',
        'testkit/studio-release.json',
        'testkit/corpus-manifest.json',
        'browser/studio-assets.json',
        'browser/' . $browserAsset->path,
        'browser/' . $enhancementAsset->path,
    ];
    foreach ($redistributionAssets as $asset) {
        $directPaths[] = 'browser/' . $asset->path;
    }
    $files = [];
    foreach ($directPaths as $path) {
        $files[] = ['file' => $path, 'sha256' => hash('sha256', $expected[$path])];
    }

    $packages = [];
    foreach ($packageEvidence as $entry) {
        $packages[] = [
            'name' => $entry->name,
            'version' => $entry->version,
            'tarball' => $entry->tarball,
            'bytes' => $entry->bytes,
            'sha256' => $entry->sha256,
            'shasum' => $entry->shasum,
            'integrity' => $entry->integrity,
            'attestation' => $entry->attestation,
        ];
    }

    $archive = $evidence->browserArchive ?? null;
    $candidate = $archive instanceof stdClass ? ($archive->candidate ?? null) : null;
    $archiveCandidate = $candidate instanceof stdClass ? ($candidate->archive ?? null) : null;
    $checksumCandidate = $candidate instanceof stdClass ? ($candidate->checksum ?? null) : null;
    $publication = $archive instanceof stdClass ? ($archive->publication ?? null) : null;
    if (
        !$archive instanceof stdClass
        || !$archiveCandidate instanceof stdClass
        || !$checksumCandidate instanceof stdClass
        || !$publication instanceof stdClass
        || ($archive->archiveStem ?? null) !== ($release->browserArtifacts->authoringArchive->archiveStem ?? null)
        || ($archive->status ?? null) !== 'verified-local-candidate'
        || ($publication->status ?? null) !== 'unavailable'
        || !is_string($archive->reason ?? null)
    ) {
        throw new RuntimeException('Publication evidence must retain the blocked verified browser candidate.');
    }

    return [
        'pin' => 'kumwe-producer-studio-contract',
        'source' => [
            'repository' => $evidence->source->repository,
            'kind' => 'provenance-backed-npm-release',
            'release' => $release->release,
            'commit' => $evidence->source->commit,
            'workflow' => $evidence->source->workflow,
        ],
        'release_record' => [
            'release' => $release->release,
            'file' => 'studio-release.json',
            'sha256' => hash('sha256', $expected['studio-release.json']),
        ],
        'protocol_version' => $release->protocolVersion,
        'corpus_manifest_digest' => $release->corpusManifestDigest,
        'claimed_profiles' => $release->claimedProfiles,
        'packages' => get_object_vars($release->packages),
        'package_provenance' => $packages,
        'browser_artifacts' => [
            'manifest' => [
                'file' => 'browser/studio-assets.json',
                'package' => '@kumwe/studio',
                'package_path' => 'dist/browser/studio-assets.json',
                'sha256' => hash('sha256', $expected['browser/studio-assets.json']),
            ],
            'authoring_archive' => [
                'archive_stem' => $archive->archiveStem,
                'status' => $archive->status,
                'archive_file' => $archiveCandidate->file,
                'archive_bytes' => $archiveCandidate->bytes,
                'archive_budget_bytes' => $archiveCandidate->budgetBytes,
                'archive_sha256' => $archiveCandidate->sha256,
                'archive_sha512' => $archiveCandidate->sha512,
                'archive_integrity' => $archiveCandidate->integrity,
                'manifest_sha256' => $archiveCandidate->manifestSha256,
                'member_count' => $archiveCandidate->members,
                'checksum_file' => $checksumCandidate->file,
                'checksum_bytes' => $checksumCandidate->bytes,
                'checksum_sha256' => $checksumCandidate->sha256,
                'publication_status' => $publication->status,
                'expected_tag' => $publication->expectedTag,
                'expected_release_url' => $publication->expectedReleaseUrl,
                'expected_archive_url' => $publication->expectedArchiveUrl,
                'expected_checksum_url' => $publication->expectedChecksumUrl,
                'reason' => $archive->reason,
            ],
            'resolved_assets' => [
                producerImportPinnedAsset($browserAsset, '@kumwe/studio'),
                producerImportPinnedAsset($enhancementAsset, '@kumwe/studio-renderer-web'),
            ],
            'redistribution_files' => array_map(
                static fn (stdClass $asset): array => producerImportPinnedRedistributionFile(
                    $asset,
                    $expected['browser/' . $asset->path],
                ),
                $redistributionAssets,
            ),
        ],
        'release_readiness' => [
            'status' => 'blocked',
            'blockers' => [$archive->reason],
        ],
        'files' => $files,
    ];
}

/** @return array<string, mixed> */
function producerImportPinnedRedistributionFile(stdClass $asset, string $bytes): array
{
    return [
        'role' => $asset->role,
        'file' => 'browser/' . $asset->path,
        'package' => '@kumwe/studio',
        'package_path' => 'dist/browser/' . $asset->path,
        'bytes' => $asset->bytes,
        'media_type' => $asset->mediaType,
        'integrity' => $asset->integrity,
        'sha256' => hash('sha256', $bytes),
    ];
}

/** @return array<string, mixed> */
function producerImportPinnedAsset(stdClass $asset, string $package): array
{
    return [
        'role' => $asset->role,
        'file' => 'browser/' . $asset->path,
        'package' => $package,
        'package_path' => 'dist/browser/' . $asset->path,
        'bytes' => $asset->bytes,
        'budget_bytes' => $asset->budgetBytes,
        'content_hash' => $asset->contentHash,
        'integrity' => $asset->integrity,
        'minified' => $asset->minified,
    ];
}

/** Encode one deterministic reviewed JSON document. */
function producerImportJson(array $document): string
{
    $bytes = json_encode(
        $document,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );

    return $bytes . "\n";
}

/**
 * Atomically replace only one dedicated contract resource directory.
 *
 * Injectable filesystem operations exist only for adversarial transaction
 * tests; production uses the native same-filesystem rename and scoped tree
 * remover. A checked rollback restores the old target after an install
 * failure. If rollback itself fails, both generated recovery trees remain
 * untouched and the error is explicitly a recovery-required state. Once the
 * new target is live, backup cleanup cannot turn that committed install into
 * a false failed-import result.
 *
 * @param array<string, string>                     $files
 * @param null|callable(string, string): bool        $rename
 * @param null|callable(string, string): void        $removeTree
 * @param null|callable(string): void                $warning
 */
function producerImportReplace(
    array $files,
    string $target = PRODUCER_IMPORT_ROOT,
    ?callable $rename = null,
    ?callable $removeTree = null,
    ?callable $warning = null,
): void {
    $parentArgument = dirname($target);
    $parent = realpath($parentArgument);
    $resolvedTarget = is_link($target) ? false : realpath($target);
    if (
        $parent === false
        || !is_dir($parent)
        || is_link($parentArgument)
        || $resolvedTarget === false
        || !is_dir($resolvedTarget)
        || dirname($resolvedTarget) !== $parent
        || basename($resolvedTarget) !== basename($target)
    ) {
        throw new RuntimeException('Contract replacement target is not one ordinary scoped directory.');
    }
    $target = $resolvedTarget;
    $move = $rename ?? static fn (string $from, string $to): bool => rename($from, $to);
    $remove = $removeTree ?? static function (string $root, string $requiredParent): void {
        producerImportRemoveTree($root, $requiredParent);
    };
    $warn = $warning ?? static function (string $message): void {
        fwrite(STDERR, $message . "\n");
    };
    $temporary = $parent . '/.studio-contract-import-' . bin2hex(random_bytes(8));
    $backup = $parent . '/.studio-contract-backup-' . bin2hex(random_bytes(8));
    if (!mkdir($temporary, 0700, true)) {
        throw new RuntimeException('Could not create the isolated contract import directory.');
    }

    try {
        foreach ($files as $relative => $bytes) {
            if (!is_string($relative) || !is_string($bytes) || !producerImportSafeRelative($relative)) {
                throw new RuntimeException('Importer produced an unsafe output path.');
            }
            $path = $temporary . '/' . $relative;
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
                throw new RuntimeException('Could not create an imported contract directory.');
            }
            if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) {
                throw new RuntimeException('Could not write complete imported contract bytes.');
            }
        }
    } catch (Throwable $error) {
        if (is_dir($temporary)) {
            try {
                $remove($temporary, $parent);
            } catch (Throwable $cleanupError) {
                throw new RuntimeException(
                    'Contract generation failed and its generated import tree could not be cleaned.',
                    0,
                    $cleanupError,
                );
            }
        }
        throw $error;
    }

    try {
        $move($target, $backup);
    } catch (Throwable $error) {
        // Filesystem state below, not a callback claim, decides the transaction.
    }
    $isolated = is_dir($backup) && !is_link($backup) && !file_exists($target);
    if (!$isolated) {
        if (!file_exists($target)) {
            throw new RuntimeException(
                'Studio contract recovery required: the target is absent and no ordinary generated '
                    . 'backup state can be trusted; the candidate remains in its generated import tree.',
            );
        }
        if (is_dir($temporary) && !is_link($temporary)) {
            $remove($temporary, $parent);
        }
        throw new RuntimeException('Could not isolate the prior contract generation.');
    }

    $installError = null;
    try {
        $move($temporary, $target);
    } catch (Throwable $error) {
        $installError = $error;
    }
    $installed = is_dir($target) && !is_link($target) && !file_exists($temporary);
    if (!$installed) {
        $installError ??= new RuntimeException('Could not install the imported contract generation.');
        try {
            $move($backup, $target);
        } catch (Throwable $rollbackError) {
            // Filesystem state below, not a callback claim, decides rollback.
        }
        $restored = is_dir($target) && !is_link($target) && !file_exists($backup);
        if (!$restored) {
            throw new RuntimeException(
                'Studio contract recovery required: the target was not restored; the prior generation '
                    . 'remains in its generated backup tree and the candidate remains in its generated '
                    . 'import tree.',
                0,
                $installError,
            );
        }
        if (is_dir($temporary) && !is_link($temporary)) {
            try {
                $remove($temporary, $parent);
            } catch (Throwable $cleanupError) {
                throw new RuntimeException(
                    'The prior contract generation was restored, but the generated import tree remains.',
                    0,
                    $cleanupError,
                );
            }
        }
        throw new RuntimeException(
            'Could not install the imported contract generation; the prior generation was restored.',
            0,
            $installError,
        );
    }

    try {
        $remove($backup, $parent);
        if (file_exists($backup)) {
            throw new RuntimeException('Generated backup cleanup returned without removing its tree.');
        }
    } catch (Throwable $cleanupError) {
        try {
            $warn(
                'Studio contract import warning: the new generation is installed, but its generated '
                    . 'prior-generation backup could not be completely removed.',
            );
        } catch (Throwable $warningError) {
            // Warning delivery cannot turn a committed generation into a false failure.
        }
    }
}

/** Remove only a generated sibling tree after checking its exact parent. */
function producerImportRemoveTree(string $root, string $requiredParent): void
{
    if (dirname($root) !== $requiredParent || !is_dir($root) || is_link($root)) {
        throw new RuntimeException('Refusing to remove a non-import tree.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        if ($entry->isLink() || $entry->isFile()) {
            if (!unlink($entry->getPathname())) {
                throw new RuntimeException('Could not remove a superseded import file.');
            }
        } elseif (!rmdir($entry->getPathname())) {
            throw new RuntimeException('Could not remove a superseded import directory.');
        }
    }
    if (!rmdir($root)) {
        throw new RuntimeException('Could not remove the superseded import root.');
    }
}

/** Reject traversal, empty segments, links-by-spelling, and platform ambiguity. */
function producerImportSafeRelative(string $path): bool
{
    if (
        $path === ''
        || str_starts_with($path, '/')
        || str_contains($path, '\\')
        || !mb_check_encoding($path, 'UTF-8')
        || preg_match('/[\x00-\x1f\x7f]/', $path) === 1
    ) {
        return false;
    }
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return false;
        }
    }

    return true;
}

/** Return Studio's canonical SHA-256 SRI spelling for bytes. */
function producerImportSri(string $bytes): string
{
    return 'sha256-' . base64_encode(hash('sha256', $bytes, true));
}
