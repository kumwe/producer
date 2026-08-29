<?php

declare(strict_types=1);

namespace Kumwe\Producer\Schema;

use Kumwe\Producer\Canonical\CanonicalJson;

/**
 * Read-only access to files admitted by Producer's pinned Studio testkit
 * manifest.
 *
 * Consumers can reuse the exact released fixtures without copying the corpus
 * or guessing a Composer vendor layout. The API never exposes a corpus root:
 * every requested relative path must be an exact manifest member and its bytes
 * must still match the released SHA-256 digest. Directory traversal, symlinks
 * and unmanifested lookups are refused; package paths never leave this class.
 *
 * @since   0.2.0
 */
final class StudioContractResources
{
    /**
     * Maximum bytes read from one release, manifest, pin or selected fixture.
     *
     * @since   0.2.0
     */
    private const MAX_RESOURCE_BYTES = 1048576;

    /**
     * Static utility; never instantiated.
     *
     * @since   0.2.0
     */
    private function __construct()
    {
    }

    /**
     * Return the typed immutable coordinates of the exact installed release.
     *
     * Producer proves the package, protocol and testkit release records are
     * byte-identical and that PIN.json binds those bytes before constructing
     * the shared value. Consumers therefore compare coordinates without
     * decoding or reinterpreting Producer's release authority.
     *
     * @throws \RuntimeException When installed release or pin bytes are missing, malformed or drifted.
     *
     * @since   0.2.0
     */
    public static function releaseRecord(): StudioContractRelease
    {
        /** @var StudioContractRelease|null $shared */
        static $shared = null;
        if ($shared !== null) {
            return $shared;
        }

        $root = dirname(self::testkitRoot());
        $recordPath = $root . '/studio-release.json';
        $recordBytes = self::fileBytes($recordPath);
        $directFiles = [
            'studio-release.json' => $recordBytes,
            'protocol/studio-release.json' => self::fileBytes($root . '/protocol/studio-release.json'),
            'protocol/schemas/manifest.json' => self::fileBytes($root . '/protocol/schemas/manifest.json'),
            'testkit/studio-release.json' => self::fileBytes($root . '/testkit/studio-release.json'),
            'testkit/corpus-manifest.json' => self::fileBytes($root . '/testkit/corpus-manifest.json'),
        ];
        foreach (['protocol/studio-release.json', 'testkit/studio-release.json'] as $copy) {
            if (!hash_equals($recordBytes, $directFiles[$copy])) {
                throw new \RuntimeException('The installed Studio release records are not byte-identical.');
            }
        }

        $sha256 = hash('sha256', $recordBytes);
        $pin = self::objectDocument($root . '/PIN.json');
        $binding = $pin->release_record ?? null;
        $record = self::decodeObject($recordBytes, $recordPath);
        if (self::sortedMemberNames($pin) !== [
            'claimed_profiles',
            'corpus_manifest_digest',
            'files',
            'packages',
            'pin',
            'protocol_version',
            'release_record',
            'source',
        ]
            || self::sortedMemberNames($record) !== [
                'claimedProfiles',
                'contractVersion',
                'corpusManifestDigest',
                'kind',
                'packages',
                'protocolVersion',
                'release',
            ]
            || ($record->kind ?? null) !== 'studio-release'
            || ($pin->pin ?? null) !== 'kumwe-producer-studio-contract'
            || !$binding instanceof \stdClass
            || ($binding->file ?? null) !== 'studio-release.json'
            || ($binding->sha256 ?? null) !== $sha256
            || ($binding->release ?? null) !== ($record->release ?? null)
        ) {
            throw new \RuntimeException('The installed Studio PIN does not bind its release-record bytes.');
        }

        $source = $pin->source ?? null;
        if (!$source instanceof \stdClass
            || ($source->repository ?? null) !== 'https://github.com/kumwe/studio'
            || ($source->kind ?? null) !== 'coordinated-release'
            || ($source->release ?? null) !== ($record->release ?? null)
        ) {
            throw new \RuntimeException('The installed Studio PIN does not name its coordinated release.');
        }
        self::assertDirectFileBindings($pin->files ?? null, $directFiles);

        $profiles = $record->claimedProfiles ?? null;
        $packageObject = $record->packages ?? null;
        if (!is_array($profiles) || !array_is_list($profiles) || !$packageObject instanceof \stdClass) {
            throw new \RuntimeException('The installed Studio release record has malformed profiles or packages.');
        }
        $claimedProfiles = [];
        foreach ($profiles as $profile) {
            if (!is_string($profile)) {
                throw new \RuntimeException('The installed Studio release has a non-string profile claim.');
            }
            $claimedProfiles[] = $profile;
        }
        $packages = self::packageMap($packageObject);
        if (($pin->protocol_version ?? null) !== ($record->protocolVersion ?? null)
            || ($pin->corpus_manifest_digest ?? null) !== ($record->corpusManifestDigest ?? null)
            || ($pin->claimed_profiles ?? null) !== $profiles
            || !($pin->packages ?? null) instanceof \stdClass
            || self::packageMap($pin->packages) !== $packages
        ) {
            throw new \RuntimeException('The installed Studio PIN coordinates differ from its release record.');
        }

        try {
            $shared = new StudioContractRelease(
                self::requiredString($record, 'contractVersion'),
                self::requiredString($record, 'release'),
                self::requiredString($record, 'protocolVersion'),
                self::requiredString($record, 'corpusManifestDigest'),
                $claimedProfiles,
                $packages,
                $sha256
            );
        } catch (\InvalidArgumentException $error) {
            throw new \RuntimeException('The installed Studio release coordinates are malformed.', 0, $error);
        }

        return $shared;
    }

    /**
     * Prove that PIN.json binds exactly the five release and manifest files.
     *
     * @param mixed                 $entries Exact ordered PIN file entries.
     * @param array<string, string> $bytes   Required relative files and bytes.
     *
     * @since   0.2.0
     */
    private static function assertDirectFileBindings(mixed $entries, array $bytes): void
    {
        if (!is_array($entries) || !array_is_list($entries)) {
            throw new \RuntimeException('The installed Studio PIN has no ordered direct-file bindings.');
        }
        $bindings = [];
        foreach ($entries as $entry) {
            $file = $entry instanceof \stdClass ? ($entry->file ?? null) : null;
            $sha256 = $entry instanceof \stdClass ? ($entry->sha256 ?? null) : null;
            if (!is_string($file)
                || !isset($bytes[$file])
                || !is_string($sha256)
                || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1
                || isset($bindings[$file])
                || !hash_equals(hash('sha256', $bytes[$file]), $sha256)
            ) {
                throw new \RuntimeException('The installed Studio PIN has a malformed direct-file binding.');
            }
            $bindings[$file] = true;
        }
        $required = array_fill_keys(array_keys($bytes), true);
        ksort($bindings);
        ksort($required);
        if ($bindings !== $required) {
            throw new \RuntimeException('The installed Studio PIN direct-file set is incomplete or expanded.');
        }
    }

    /**
     * Narrow and sort one decoded package-version object.
     *
     * @param \stdClass $packages Decoded package coordinate object.
     *
     * @return array<string, string>
     *
     * @since   0.2.0
     */
    private static function packageMap(\stdClass $packages): array
    {
        $map = [];
        foreach (get_object_vars($packages) as $package => $version) {
            if (!is_string($version)) {
                throw new \RuntimeException('The installed Studio release has a non-string package version.');
            }
            $map[(string) $package] = $version;
        }
        ksort($map);

        return $map;
    }

    /**
     * Return object member names in deterministic lexical order.
     *
     * @param \stdClass $value Decoded object.
     *
     * @return list<string>
     *
     * @since   0.2.0
     */
    private static function sortedMemberNames(\stdClass $value): array
    {
        $members = array_map('strval', array_keys(get_object_vars($value)));
        sort($members, SORT_STRING);

        return $members;
    }

    /**
     * Read the exact testkit corpus-manifest bytes bound by the typed release.
     *
     * This dedicated byte reader supports consumer conformance tooling while
     * keeping the manifest itself outside the group-member locator and never
     * exposing a corpus root or arbitrary filesystem lookup.
     *
     * @throws \RuntimeException When the manifest is absent or differs from the release digest.
     *
     * @since   0.2.0
     */
    public static function testkitManifestBytes(): string
    {
        $path = self::testkitRoot() . '/corpus-manifest.json';
        $bytes = self::fileBytes($path);
        $actual = 'sha256-' . base64_encode(hash('sha256', $bytes, true));
        if (!hash_equals(self::releaseRecord()->corpusManifestDigest(), $actual)) {
            throw new \RuntimeException('The installed Studio corpus manifest differs from its release digest.');
        }

        return $bytes;
    }

    /**
     * Read one exact testkit file by its corpus-manifest-relative path.
     *
     * Examples are `fixtures/blueprint.product.example.json` and
     * `vectors/host-sequence/artifact.publish.changed-intent.sequence.json`.
     * @param string $relative Manifest-relative testkit path.
     *
     * @throws \InvalidArgumentException When the path is unsafe or is not a released manifest member.
     * @throws \RuntimeException         When the installed release manifest or selected file is
     *                                   missing, malformed, linked, relocated or digest-mismatched.
     *
     * @since   0.2.0
     */
    public static function testkitBytes(string $relative): string
    {
        if (!self::safeRelative($relative)) {
            throw new \InvalidArgumentException('A Studio testkit path must be a safe normalized relative path.');
        }
        $files = self::testkitFiles();
        $digest = $files[$relative] ?? null;
        if ($digest === null) {
            throw new \InvalidArgumentException(sprintf(
                '"%s" is not a member of the pinned Studio testkit manifest.',
                $relative
            ));
        }

        $root = self::testkitRoot();
        $candidate = $root . '/' . $relative;
        $resolved = realpath($candidate);
        if ($resolved === false
            || !is_file($candidate)
            || is_link($candidate)
            || $resolved !== $candidate
            || !str_starts_with($resolved, $root . '/')
        ) {
            throw new \RuntimeException('The manifested Studio testkit file is missing or leaves its package root.');
        }
        $bytes = self::fileBytes($resolved);
        $actual = 'sha256-' . base64_encode(hash('sha256', $bytes, true));
        if (!hash_equals($digest, $actual)) {
            throw new \RuntimeException('The manifested Studio testkit file no longer matches its released digest.');
        }

        return $bytes;
    }

    /**
     * Build the exact testkit membership table from its release-bound manifest.
     *
     * @return array<string, string> Manifest-relative file to SRI digest.
     *
     * @since   0.2.0
     */
    private static function testkitFiles(): array
    {
        /** @var array<string, string>|null $shared */
        static $shared = null;
        if ($shared !== null) {
            return $shared;
        }

        $manifestPath = self::testkitRoot() . '/corpus-manifest.json';
        $manifestBytes = self::testkitManifestBytes();
        $manifest = self::decodeObject($manifestBytes, $manifestPath);
        $groups = $manifest->groups ?? null;
        if (!is_array($groups) || !array_is_list($groups)) {
            throw new \RuntimeException('The installed Studio corpus manifest has no ordered groups list.');
        }

        $files = [];
        foreach ($groups as $group) {
            $path = $group instanceof \stdClass ? ($group->path ?? null) : null;
            $entries = $group instanceof \stdClass ? ($group->files ?? null) : null;
            if (!is_string($path)
                || !self::safeRelative($path)
                || !is_array($entries)
                || !array_is_list($entries)
            ) {
                throw new \RuntimeException('The installed Studio corpus manifest carries a malformed group.');
            }
            foreach ($entries as $entry) {
                $file = $entry instanceof \stdClass ? ($entry->file ?? null) : null;
                $digest = $entry instanceof \stdClass ? ($entry->digest ?? null) : null;
                $relative = is_string($file) ? $path . '/' . $file : '';
                if (!is_string($file)
                    || !self::safeRelative($file)
                    || !is_string($digest)
                    || !self::sha256Sri($digest)
                    || isset($files[$relative])
                ) {
                    throw new \RuntimeException('The installed Studio corpus manifest carries a malformed file.');
                }
                $files[$relative] = $digest;
            }
        }
        ksort($files);
        $shared = $files;

        return $shared;
    }

    /**
     * Resolve the package-owned testkit root without exposing it publicly.
     *
     * @since   0.2.0
     */
    private static function testkitRoot(): string
    {
        $expected = dirname(__DIR__, 2) . '/resources/studio-contract/testkit';
        $resolved = realpath($expected);
        if ($resolved === false || !is_dir($expected) || is_link($expected) || $resolved !== $expected) {
            throw new \RuntimeException('The installed Studio testkit root is missing or linked.');
        }

        return $resolved;
    }

    /**
     * Decode one required JSON object from a package file.
     *
     * @param string $path Absolute package file path.
     *
     * @since   0.2.0
     */
    private static function objectDocument(string $path): \stdClass
    {
        return self::decodeObject(self::fileBytes($path), $path);
    }

    /**
     * Read one required string member from a decoded release record.
     *
     * @param \stdClass $record Decoded release record.
     * @param string    $member Required member name.
     *
     * @since   0.2.0
     */
    private static function requiredString(\stdClass $record, string $member): string
    {
        $value = $record->{$member} ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException('The installed Studio release record is missing ' . $member . '.');
        }

        return $value;
    }

    /**
     * Decode bytes as a JSON object with a location-bearing failure.
     *
     * @param string $bytes JSON document bytes.
     * @param string $path  Path named by a refusal.
     *
     * @since   0.2.0
     */
    private static function decodeObject(string $bytes, string $path): \stdClass
    {
        try {
            $decoded = CanonicalJson::decode($bytes);
        } catch (\JsonException $error) {
            throw new \RuntimeException('The installed Studio JSON file is malformed: ' . $path, 0, $error);
        }
        if (!$decoded instanceof \stdClass) {
            throw new \RuntimeException('The installed Studio JSON file must contain an object: ' . $path);
        }

        return $decoded;
    }

    /**
     * Read one required regular, unlinked package file.
     *
     * @param string $path Absolute package file path.
     *
     * @since   0.2.0
     */
    private static function fileBytes(string $path): string
    {
        $pathStat = @lstat($path);
        if (!is_array($pathStat) || !self::regularFileStat($pathStat)) {
            throw new \RuntimeException('A required Studio package file is missing or linked: ' . $path);
        }
        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) {
            throw new \RuntimeException('A required Studio package file is unreadable: ' . $path);
        }
        try {
            $before = fstat($handle);
            if (!is_array($before)
                || !self::regularFileStat($before)
                || !self::sameFileIdentity($pathStat, $before)
                || $before['size'] > self::MAX_RESOURCE_BYTES
            ) {
                throw new \RuntimeException('A Studio package file changed or exceeds its byte bound: ' . $path);
            }
            $bytes = stream_get_contents($handle, self::MAX_RESOURCE_BYTES + 1);
            $after = fstat($handle);
            if (!is_string($bytes)
                || strlen($bytes) > self::MAX_RESOURCE_BYTES
                || !is_array($after)
                || !self::sameFileSnapshot($before, $after)
            ) {
                throw new \RuntimeException('A Studio package file changed while it was read: ' . $path);
            }
        } finally {
            fclose($handle);
        }

        return $bytes;
    }

    /**
     * Whether a filesystem stat describes an ordinary file.
     *
     * @param array<int|string, int> $stat Filesystem stat.
     *
     * @since   0.2.0
     */
    private static function regularFileStat(array $stat): bool
    {
        return isset($stat['mode']) && ($stat['mode'] & 0170000) === 0100000;
    }

    /**
     * Whether a path stat and opened-handle stat identify the same file.
     *
     * @param array<int|string, int> $pathStat Path stat.
     * @param array<int|string, int> $openStat Opened-handle stat.
     *
     * @since   0.2.0
     */
    private static function sameFileIdentity(array $pathStat, array $openStat): bool
    {
        return isset($pathStat['dev'], $pathStat['ino'], $openStat['dev'], $openStat['ino'])
            && $pathStat['dev'] === $openStat['dev']
            && $pathStat['ino'] === $openStat['ino'];
    }

    /**
     * Whether an opened file retained identity and content metadata while read.
     *
     * @param array<int|string, int> $before Stat before reading.
     * @param array<int|string, int> $after  Stat after reading.
     *
     * @since   0.2.0
     */
    private static function sameFileSnapshot(array $before, array $after): bool
    {
        foreach (['dev', 'ino', 'size', 'mtime', 'ctime'] as $member) {
            if (!isset($before[$member], $after[$member]) || $before[$member] !== $after[$member]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a path is normalized, bounded UTF-8 and cannot traverse.
     *
     * @param string $relative Candidate manifest-relative path.
     *
     * @since   0.2.0
     */
    private static function safeRelative(string $relative): bool
    {
        if ($relative === ''
            || strlen($relative) > 500
            || !mb_check_encoding($relative, 'UTF-8')
            || str_starts_with($relative, '/')
            || str_contains($relative, '\\')
            || preg_match('/[\x00-\x1f\x7f]/', $relative) === 1
        ) {
            return false;
        }
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a digest uses Studio's canonical SHA-256 SRI spelling.
     *
     * @param string $digest Candidate digest.
     *
     * @since   0.2.0
     */
    private static function sha256Sri(string $digest): bool
    {
        return preg_match('/^sha256-[A-Za-z0-9+\/]{42}[AEIMQUYcgkosw048]=$/', $digest) === 1;
    }
}
