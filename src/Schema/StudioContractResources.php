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
     * Exact locally reproduced, non-vendored Studio beta.2 outer archive.
     *
     * @since 0.2.0
     */
    private const BROWSER_ARCHIVE_BYTES = 1401344;

    /**
     * Studio's fixed outer-archive publication budget.
     *
     * @since 0.2.0
     */
    private const BROWSER_ARCHIVE_BUDGET_BYTES = 2097152;

    /**
     * Exact regular-file ustar member count: manifest plus 73 assets.
     *
     * @since 0.2.0
     */
    private const BROWSER_ARCHIVE_MEMBERS = 74;

    /**
     * Exact reviewed Studio beta.2 outer-archive SHA-256.
     *
     * @since 0.2.0
     */
    private const BROWSER_ARCHIVE_SHA256 =
        'e1bd88fa0bf6170e098bb50235783137d8d1aea9b28421a700b550886ffbab01';

    /**
     * Exact reviewed Studio beta.2 outer-archive SHA-512.
     *
     * @since 0.2.0
     */
    private const BROWSER_ARCHIVE_SHA512 =
        '630a33ebf6ea0321559fdc78644459225544f1621c91e442ced8a357a1d68501'
        . 'd09715f5c660526be819532488fc80fa5c53536ab9060e68bac80fdbd90ed764';

    /**
     * Exact reviewed detached-checksum byte count.
     *
     * @since 0.2.0
     */
    private const BROWSER_CHECKSUM_BYTES = 115;

    /**
     * Exact reviewed detached-checksum SHA-256.
     *
     * @since 0.2.0
     */
    private const BROWSER_CHECKSUM_SHA256 =
        '25d9d43978e9bf156422794f668dcceba612e22c7ee2236b47f78d066434ddf0';

    /**
     * Single remaining release blocker until both governed assets exist.
     *
     * @since 0.2.0
     */
    private const BROWSER_ARCHIVE_BLOCKER =
        'The exact Studio beta.2 browser archive and detached checksum are locally reproduced and fully '
        . 'verified, but the governed GitHub prerelease does not publish both assets yet.';

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
        $pin = self::objectDocument($root . '/PIN.json');
        $recordPath = $root . '/studio-release.json';
        $recordBytes = self::fileBytes($recordPath);
        $directFiles = [
            'studio-release.json' => $recordBytes,
            'protocol/studio-release.json' => self::fileBytes($root . '/protocol/studio-release.json'),
            'protocol/schemas/manifest.json' => self::fileBytes($root . '/protocol/schemas/manifest.json'),
            'testkit/studio-release.json' => self::fileBytes($root . '/testkit/studio-release.json'),
            'testkit/corpus-manifest.json' => self::fileBytes($root . '/testkit/corpus-manifest.json'),
        ];
        $pinFiles = $pin->files ?? null;
        if (!is_array($pinFiles) || !array_is_list($pinFiles) || count($pinFiles) !== 22) {
            throw new \RuntimeException('The installed Studio PIN has no ordered direct-file bindings.');
        }
        foreach ($pinFiles as $entry) {
            $file = $entry instanceof \stdClass ? ($entry->file ?? null) : null;
            if (
                is_string($file)
                && str_starts_with($file, 'browser/')
                && self::safeRelative($file)
            ) {
                $directFiles[$file] = self::fileBytes($root . '/' . $file);
            }
        }
        foreach (['protocol/studio-release.json', 'testkit/studio-release.json'] as $copy) {
            if (!hash_equals($recordBytes, $directFiles[$copy])) {
                throw new \RuntimeException('The installed Studio release records are not byte-identical.');
            }
        }

        $sha256 = hash('sha256', $recordBytes);
        $binding = $pin->release_record ?? null;
        $record = self::decodeObject($recordBytes, $recordPath);
        if (
            self::sortedMemberNames($pin) !== [
                'browser_artifacts',
                'claimed_profiles',
                'corpus_manifest_digest',
                'files',
                'package_provenance',
                'packages',
                'pin',
                'protocol_version',
                'release_readiness',
                'release_record',
                'source',
            ]
            || self::sortedMemberNames($record) !== [
                'browserArtifacts',
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
        $sourceCommit = $source instanceof \stdClass ? ($source->commit ?? null) : null;
        if (
            !$source instanceof \stdClass
            || ($source->repository ?? null) !== 'https://github.com/kumwe/studio'
            || ($source->kind ?? null) !== 'provenance-backed-npm-release'
            || ($source->release ?? null) !== ($record->release ?? null)
            || !is_string($sourceCommit)
            || preg_match('/^[a-f0-9]{40}$/', $sourceCommit) !== 1
            || !is_string($source->workflow ?? null)
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
        $packageIntegrities = self::packageIntegrities(
            $pin->package_provenance ?? null,
            $packages,
            $sourceCommit,
        );
        $browserArtifacts = self::browserArtifactLocators(
            $record->browserArtifacts ?? null,
            self::requiredString($record, 'release'),
        );
        [$releaseReady, $releaseBlockers] = self::releaseReadiness($pin->release_readiness ?? null);
        if (
            ($pin->protocol_version ?? null) !== ($record->protocolVersion ?? null)
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
                $sourceCommit,
                $packageIntegrities,
                $browserArtifacts,
                $releaseReady,
                $releaseBlockers,
                $sha256,
            );
        } catch (\InvalidArgumentException $error) {
            throw new \RuntimeException('The installed Studio release coordinates are malformed.', 0, $error);
        }

        try {
            self::browserAssets();
        } catch (\Throwable $error) {
            $shared = null;
            throw $error;
        }

        return $shared;
    }

    /**
     * Prove that PIN.json binds the release records, manifests, both
     * executable browser assets, and every redistributed notice/license
     * member exactly.
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
            if (
                !is_string($file)
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
     * Verify one public npm envelope record for every coordinated package.
     *
     * @param mixed                 $entries      Ordered PIN provenance entries.
     * @param array<string, string> $packages     Exact coordinated package versions.
     * @param string                $sourceCommit Provenance-authenticated source commit.
     *
     * @return array<string, string> Package name to SHA-512 registry integrity.
     *
     * @since 0.2.0
     */
    private static function packageIntegrities(
        mixed $entries,
        array $packages,
        string $sourceCommit,
    ): array {
        if (
            !is_array($entries)
            || !array_is_list($entries)
            || preg_match('/^[a-f0-9]{40}$/', $sourceCommit) !== 1
        ) {
            throw new \RuntimeException('The installed Studio PIN has no package provenance family.');
        }
        $integrities = [];
        foreach ($entries as $entry) {
            $name = $entry instanceof \stdClass ? ($entry->name ?? null) : null;
            $sha256 = $entry instanceof \stdClass ? ($entry->sha256 ?? null) : null;
            $shasum = $entry instanceof \stdClass ? ($entry->shasum ?? null) : null;
            $integrity = $entry instanceof \stdClass ? ($entry->integrity ?? null) : null;
            if (
                !is_string($name)
                || !isset($packages[$name])
                || isset($integrities[$name])
                || ($entry->version ?? null) !== $packages[$name]
                || !is_string($entry->tarball ?? null)
                || !str_starts_with($entry->tarball, 'https://registry.npmjs.org/')
                || !is_int($entry->bytes ?? null)
                || $entry->bytes < 1
                || !is_string($sha256)
                || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1
                || !is_string($shasum)
                || preg_match('/^[a-f0-9]{40}$/', $shasum) !== 1
                || !is_string($integrity)
                || preg_match(
                    '/^sha512-[A-Za-z0-9+\/]+={0,2}$/',
                    $integrity,
                ) !== 1
                || !is_string($entry->attestation ?? null)
                || !str_starts_with($entry->attestation, 'https://registry.npmjs.org/-/npm/v1/attestations/')
            ) {
                throw new \RuntimeException('The installed Studio PIN has malformed package provenance.');
            }
            $integrities[$name] = $integrity;
        }
        ksort($integrities, SORT_STRING);
        $expected = $packages;
        ksort($expected, SORT_STRING);
        if (array_keys($integrities) !== array_keys($expected)) {
            throw new \RuntimeException('The installed Studio PIN package provenance is incomplete or expanded.');
        }

        return $integrities;
    }

    /**
     * Build the closed browser locator value from the release record.
     *
     * @param mixed  $value   Decoded browserArtifacts member.
     * @param string $release Exact coordinated Studio version.
     *
     * @since 0.2.0
     */
    private static function browserArtifactLocators(mixed $value, string $release): StudioBrowserArtifacts
    {
        $manifest = $value instanceof \stdClass ? ($value->manifest ?? null) : null;
        $authoring = $value instanceof \stdClass ? ($value->authoringArchive ?? null) : null;
        $enhancement = $value instanceof \stdClass ? ($value->enhancementRuntime ?? null) : null;
        if (
            !$manifest instanceof \stdClass
            || !$authoring instanceof \stdClass
            || !$enhancement instanceof \stdClass
            || self::sortedMemberNames($value) !== ['authoringArchive', 'enhancementRuntime', 'manifest']
        ) {
            throw new \RuntimeException('The installed Studio release has malformed browser locators.');
        }

        try {
            return new StudioBrowserArtifacts(
                $release,
                self::requiredString($manifest, 'name'),
                self::requiredString($manifest, 'schema'),
                self::requiredString($authoring, 'archiveStem'),
                self::requiredString($authoring, 'assetRole'),
                self::requiredString($authoring, 'loading'),
                self::requiredString($enhancement, 'assetRole'),
                self::requiredString($enhancement, 'loading'),
                self::requiredString($enhancement, 'package'),
                self::requiredString($enhancement, 'packageBasePath'),
            );
        } catch (\InvalidArgumentException $error) {
            throw new \RuntimeException('The installed Studio browser locators are invalid.', 0, $error);
        }
    }

    /**
     * Parse the explicit release gate carried by the immutable PIN.
     *
     * @param mixed $value Decoded release_readiness member.
     *
     * @return array{0: bool, 1: list<string>}
     *
     * @since 0.2.0
     */
    private static function releaseReadiness(mixed $value): array
    {
        $status = $value instanceof \stdClass ? ($value->status ?? null) : null;
        $entries = $value instanceof \stdClass ? ($value->blockers ?? null) : null;
        if (
            !in_array($status, ['ready', 'blocked'], true)
            || !is_array($entries)
            || !array_is_list($entries)
        ) {
            throw new \RuntimeException('The installed Studio PIN has no exact release-readiness decision.');
        }
        $blockers = [];
        foreach ($entries as $entry) {
            if (!is_string($entry) || $entry === '' || !mb_check_encoding($entry, 'UTF-8')) {
                throw new \RuntimeException('The installed Studio PIN carries a malformed release blocker.');
            }
            $blockers[] = $entry;
        }
        if (($status === 'ready') === ($blockers !== [])) {
            throw new \RuntimeException('The installed Studio release decision and blockers disagree.');
        }

        return [$status === 'ready', $blockers];
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
     * Read the exact provenance-backed Studio browser manifest bytes.
     *
     * @since 0.2.0
     */
    public static function browserManifestBytes(): string
    {
        self::browserAssets();

        return self::fileBytes(dirname(self::testkitRoot()) . '/browser/studio-assets.json');
    }

    /**
     * Return one exact executable asset selected by its closed Studio role.
     *
     * @param string $role Browser-module or enhancement-runtime.
     *
     * @throws \InvalidArgumentException When the role is not one of the two released runtime roles.
     *
     * @since 0.2.0
     */
    public static function browserAsset(string $role): StudioBrowserAsset
    {
        $assets = self::browserAssets();
        if (!isset($assets[$role])) {
            throw new \InvalidArgumentException('Studio exposes only browser-module and enhancement-runtime assets.');
        }

        return $assets[$role];
    }

    /**
     * Read exact bytes only after manifest identity, digest and budget proof.
     *
     * @param string $role Browser-module or enhancement-runtime.
     *
     * @throws \InvalidArgumentException When the role is not released.
     * @throws \RuntimeException         When installed asset bytes drift.
     *
     * @since 0.2.0
     */
    public static function browserAssetBytes(string $role): string
    {
        $asset = self::browserAsset($role);
        $path = dirname(self::testkitRoot()) . '/browser/' . $asset->path();
        $bytes = self::fileBytes($path);
        $integrity = 'sha256-' . base64_encode(hash('sha256', $bytes, true));
        if (
            strlen($bytes) !== $asset->bytes()
            || !hash_equals($asset->contentHash(), hash('sha256', $bytes))
            || !hash_equals($asset->integrity(), $integrity)
            || strlen($bytes) > $asset->budgetBytes()
        ) {
            throw new \RuntimeException('The installed Studio browser asset differs from its manifest.');
        }

        return $bytes;
    }

    /**
     * Parse and prove the manifest/PIN/package chain for both runtime assets.
     *
     * @return array<string, StudioBrowserAsset>
     *
     * @since 0.2.0
     */
    private static function browserAssets(): array
    {
        /** @var array<string, StudioBrowserAsset>|null $shared */
        static $shared = null;
        if ($shared !== null) {
            return $shared;
        }

        $release = self::releaseRecord();
        $root = dirname(self::testkitRoot());
        $pin = self::objectDocument($root . '/PIN.json');
        $browserPin = $pin->browser_artifacts ?? null;
        $manifestPin = $browserPin instanceof \stdClass ? ($browserPin->manifest ?? null) : null;
        $archivePin = $browserPin instanceof \stdClass ? ($browserPin->authoring_archive ?? null) : null;
        $assetPins = $browserPin instanceof \stdClass ? ($browserPin->resolved_assets ?? null) : null;
        $redistributionPins = $browserPin instanceof \stdClass
            ? ($browserPin->redistribution_files ?? null)
            : null;
        $locators = $release->browserArtifacts();
        $manifestSha256 = $manifestPin instanceof \stdClass ? ($manifestPin->sha256 ?? null) : null;
        if (
            !$manifestPin instanceof \stdClass
            || !$archivePin instanceof \stdClass
            || !$browserPin instanceof \stdClass
            || self::sortedMemberNames($browserPin) !== [
                'authoring_archive',
                'manifest',
                'redistribution_files',
                'resolved_assets',
            ]
            || !is_array($assetPins)
            || !array_is_list($assetPins)
            || count($assetPins) !== 2
            || !is_array($redistributionPins)
            || !array_is_list($redistributionPins)
            || count($redistributionPins) !== 14
            || ($manifestPin->file ?? null) !== 'browser/' . $locators->manifestName()
            || ($manifestPin->package ?? null) !== '@kumwe/studio'
            || ($manifestPin->package_path ?? null) !== 'dist/browser/' . $locators->manifestName()
            || !is_string($manifestSha256)
            || preg_match('/^[a-f0-9]{64}$/', $manifestSha256) !== 1
        ) {
            throw new \RuntimeException('The installed Studio browser PIN is malformed.');
        }
        self::assertBlockedAuthoringArchive($archivePin, $locators, $release, $manifestSha256);

        $manifestPath = $root . '/browser/' . $locators->manifestName();
        $manifestBytes = self::fileBytes($manifestPath);
        if (!hash_equals($manifestSha256, hash('sha256', $manifestBytes))) {
            throw new \RuntimeException('The installed Studio browser manifest differs from its PIN.');
        }
        $manifest = self::decodeObject($manifestBytes, $manifestPath);
        $identity = $manifest->release ?? null;
        $entries = $manifest->assets ?? null;
        $module = $manifest->module ?? null;
        $enhancementRuntime = $manifest->enhancementRuntime ?? null;
        if (
            ($manifest->kind ?? null) !== 'studio-browser-assets'
            || ($manifest->schemaVersion ?? null) !== 1
            || !$identity instanceof \stdClass
            || ($identity->version ?? null) !== $release->release()
            || ($identity->corpusManifestDigest ?? null) !== $release->corpusManifestDigest()
            || !is_array($entries)
            || !array_is_list($entries)
            || !$module instanceof \stdClass
            || !$enhancementRuntime instanceof \stdClass
        ) {
            throw new \RuntimeException('The installed Studio browser manifest has the wrong release identity.');
        }
        self::assertBrowserRedistributionFiles($entries, $redistributionPins, $root);

        $manifestByRole = [];
        foreach ($entries as $entry) {
            $role = $entry instanceof \stdClass ? ($entry->role ?? null) : null;
            if (in_array($role, ['browser-module', 'enhancement-runtime'], true)) {
                if (isset($manifestByRole[$role])) {
                    throw new \RuntimeException('The Studio browser manifest repeats a runtime role.');
                }
                $manifestByRole[$role] = $entry;
            }
        }
        if (array_keys($manifestByRole) !== ['browser-module', 'enhancement-runtime']) {
            throw new \RuntimeException('The Studio browser manifest does not resolve both runtime roles exactly.');
        }
        $browserModuleEntry = $manifestByRole['browser-module'] ?? null;
        $enhancementEntry = $manifestByRole['enhancement-runtime'] ?? null;
        if (
            !$browserModuleEntry instanceof \stdClass
            || !$enhancementEntry instanceof \stdClass
            || ($module->entryPoint ?? null) !== ($browserModuleEntry->path ?? null)
            || ($enhancementRuntime->entryPoint ?? null)
                !== ($enhancementEntry->path ?? null)
        ) {
            throw new \RuntimeException('The Studio browser manifest entry points differ from their runtime roles.');
        }

        $pinByRole = [];
        foreach ($assetPins as $entry) {
            $role = $entry instanceof \stdClass ? ($entry->role ?? null) : null;
            if (!is_string($role) || isset($pinByRole[$role])) {
                throw new \RuntimeException('The installed Studio PIN repeats or malforms a browser role.');
            }
            $pinByRole[$role] = $entry;
        }

        $assets = [];
        foreach (
            [
                'browser-module' => '@kumwe/studio',
                'enhancement-runtime' => '@kumwe/studio-renderer-web',
            ] as $role => $package
        ) {
            $entry = $manifestByRole[$role] ?? null;
            $binding = $pinByRole[$role] ?? null;
            $path = $entry instanceof \stdClass ? ($entry->path ?? null) : null;
            $assetBytes = $entry instanceof \stdClass ? ($entry->bytes ?? null) : null;
            $budgetBytes = $entry instanceof \stdClass ? ($entry->budgetBytes ?? null) : null;
            $contentHash = $entry instanceof \stdClass ? ($entry->contentHash ?? null) : null;
            $assetIntegrity = $entry instanceof \stdClass ? ($entry->integrity ?? null) : null;
            $minified = $entry instanceof \stdClass ? ($entry->minified ?? null) : null;
            if (
                !$entry instanceof \stdClass
                || !$binding instanceof \stdClass
                || !is_string($path)
                || !self::safeRelative($path)
                || !is_int($assetBytes)
                || !is_int($budgetBytes)
                || !is_string($contentHash)
                || !is_string($assetIntegrity)
                || !is_bool($minified)
                || ($entry->mediaType ?? null) !== 'text/javascript'
                || ($binding->file ?? null) !== 'browser/' . $path
                || ($binding->package ?? null) !== $package
                || ($binding->package_path ?? null) !== 'dist/browser/' . $path
                || ($binding->bytes ?? null) !== $assetBytes
                || ($binding->budget_bytes ?? null) !== $budgetBytes
                || ($binding->content_hash ?? null) !== $contentHash
                || ($binding->integrity ?? null) !== $assetIntegrity
                || ($binding->minified ?? null) !== true
            ) {
                throw new \RuntimeException('The installed Studio PIN and browser manifest disagree.');
            }
            try {
                $asset = new StudioBrowserAsset(
                    $role,
                    $path,
                    $package,
                    $assetBytes,
                    $budgetBytes,
                    $contentHash,
                    $assetIntegrity,
                    $minified,
                );
            } catch (\InvalidArgumentException $error) {
                throw new \RuntimeException('The installed Studio browser asset is malformed.', 0, $error);
            }
            $bytes = self::fileBytes($root . '/browser/' . $path);
            $integrity = 'sha256-' . base64_encode(hash('sha256', $bytes, true));
            if (
                strlen($bytes) !== $asset->bytes()
                || strlen($bytes) > $asset->budgetBytes()
                || !hash_equals($asset->contentHash(), hash('sha256', $bytes))
                || !hash_equals($asset->integrity(), $integrity)
            ) {
                throw new \RuntimeException('The installed Studio browser bytes differ from their manifest.');
            }
            $assets[$role] = $asset;
        }

        $shared = $assets;

        return $shared;
    }

    /**
     * Verify the closed local-candidate envelope without claiming publication.
     *
     * The importer proves the unshipped tar/checksum bytes. Installed runtime
     * verification retains their exact identity and keeps release readiness
     * blocked until the governed GitHub prerelease publishes both files.
     *
     * @param \stdClass             $archive        Closed candidate PIN envelope.
     * @param StudioBrowserArtifacts $locators      Typed release artifact locators.
     * @param StudioContractRelease  $release       Exact coordinated release.
     * @param string                 $manifestSha256 Vendored manifest SHA-256.
     *
     * @since 0.2.0
     */
    private static function assertBlockedAuthoringArchive(
        \stdClass $archive,
        StudioBrowserArtifacts $locators,
        StudioContractRelease $release,
        string $manifestSha256,
    ): void {
        $stem = $locators->authoringArchiveStem();
        $archiveFile = $stem . '-' . substr(self::BROWSER_ARCHIVE_SHA256, 0, 16) . '.tar';
        $checksumFile = $archiveFile . '.sha256';
        $tag = 'studio-v' . $release->release();
        $downloadRoot = 'https://github.com/kumwe/studio/releases/download/' . $tag . '/';
        $integrity = 'sha512-' . base64_encode((string) hex2bin(self::BROWSER_ARCHIVE_SHA512));
        if (
            self::sortedMemberNames($archive) !== [
                'archive_budget_bytes',
                'archive_bytes',
                'archive_file',
                'archive_integrity',
                'archive_sha256',
                'archive_sha512',
                'archive_stem',
                'checksum_bytes',
                'checksum_file',
                'checksum_sha256',
                'expected_archive_url',
                'expected_checksum_url',
                'expected_release_url',
                'expected_tag',
                'manifest_sha256',
                'member_count',
                'publication_status',
                'reason',
                'status',
            ]
            || ($archive->archive_stem ?? null) !== $stem
            || ($archive->status ?? null) !== 'verified-local-candidate'
            || ($archive->archive_file ?? null) !== $archiveFile
            || ($archive->archive_bytes ?? null) !== self::BROWSER_ARCHIVE_BYTES
            || ($archive->archive_budget_bytes ?? null) !== self::BROWSER_ARCHIVE_BUDGET_BYTES
            || ($archive->archive_sha256 ?? null) !== self::BROWSER_ARCHIVE_SHA256
            || ($archive->archive_sha512 ?? null) !== self::BROWSER_ARCHIVE_SHA512
            || ($archive->archive_integrity ?? null) !== $integrity
            || ($archive->manifest_sha256 ?? null) !== $manifestSha256
            || ($archive->member_count ?? null) !== self::BROWSER_ARCHIVE_MEMBERS
            || ($archive->checksum_file ?? null) !== $checksumFile
            || ($archive->checksum_bytes ?? null) !== self::BROWSER_CHECKSUM_BYTES
            || ($archive->checksum_sha256 ?? null) !== self::BROWSER_CHECKSUM_SHA256
            || ($archive->publication_status ?? null) !== 'unavailable'
            || ($archive->expected_tag ?? null) !== $tag
            || ($archive->expected_release_url ?? null)
                !== 'https://github.com/kumwe/studio/releases/tag/' . $tag
            || ($archive->expected_archive_url ?? null) !== $downloadRoot . $archiveFile
            || ($archive->expected_checksum_url ?? null) !== $downloadRoot . $checksumFile
            || ($archive->reason ?? null) !== self::BROWSER_ARCHIVE_BLOCKER
            || $release->releaseReady()
            || $release->releaseBlockers() !== [self::BROWSER_ARCHIVE_BLOCKER]
        ) {
            throw new \RuntimeException('The installed Studio browser archive candidate is malformed.');
        }
    }

    /**
     * Prove the complete manifest-declared notice/license redistribution set.
     *
     * The ordered PIN entries must match every manifest member carrying a
     * `license` or `notice` role. Repeated content is valid, so path identity
     * and both digest spellings are checked independently for every file.
     *
     * @param list<mixed> $manifestEntries    Ordered Studio browser manifest assets.
     * @param list<mixed> $redistributionPins Ordered immutable package bindings.
     * @param string      $root               Installed contract resource root.
     *
     * @since 0.2.0
     */
    private static function assertBrowserRedistributionFiles(
        array $manifestEntries,
        array $redistributionPins,
        string $root,
    ): void {
        $manifestRedistribution = [];
        $seenPaths = [];
        $roleCounts = ['license' => 0, 'notice' => 0];
        foreach ($manifestEntries as $entry) {
            $role = $entry instanceof \stdClass ? ($entry->role ?? null) : null;
            if (!in_array($role, ['license', 'notice'], true)) {
                continue;
            }
            $path = $entry instanceof \stdClass ? ($entry->path ?? null) : null;
            if (!is_string($path) || isset($seenPaths[$path])) {
                throw new \RuntimeException('The Studio browser manifest repeats a redistribution path.');
            }
            $seenPaths[$path] = true;
            $roleCounts[$role]++;
            $manifestRedistribution[] = $entry;
        }
        if (
            count($manifestRedistribution) !== 14
            || $roleCounts !== ['license' => 13, 'notice' => 1]
            || count($redistributionPins) !== count($manifestRedistribution)
        ) {
            throw new \RuntimeException('The Studio browser redistribution closure is incomplete or expanded.');
        }

        foreach ($manifestRedistribution as $index => $entry) {
            $binding = $redistributionPins[$index] ?? null;
            $role = $entry->role ?? null;
            $path = $entry->path ?? null;
            $assetBytes = $entry->bytes ?? null;
            $mediaType = $entry->mediaType ?? null;
            $assetIntegrity = $entry->integrity ?? null;
            if (
                !$binding instanceof \stdClass
                || self::sortedMemberNames($entry) !== ['bytes', 'integrity', 'mediaType', 'path', 'role']
                || self::sortedMemberNames($binding) !== [
                    'bytes',
                    'file',
                    'integrity',
                    'media_type',
                    'package',
                    'package_path',
                    'role',
                    'sha256',
                ]
                || !is_string($path)
                || !self::safeRelative($path)
                || !is_int($assetBytes)
                || $assetBytes < 1
                || !is_string($assetIntegrity)
                || ($role === 'notice'
                    && ($path !== 'THIRD_PARTY_NOTICES.md' || $mediaType !== 'text/markdown'))
                || ($role === 'license' && $mediaType !== 'text/plain')
                || ($role === 'license'
                    && $path !== 'LICENSE'
                    && preg_match('#^third-party-licenses/[^/]+\.txt$#', $path) !== 1)
                || ($binding->role ?? null) !== $role
                || ($binding->file ?? null) !== 'browser/' . $path
                || ($binding->package ?? null) !== '@kumwe/studio'
                || ($binding->package_path ?? null) !== 'dist/browser/' . $path
                || ($binding->bytes ?? null) !== $assetBytes
                || ($binding->media_type ?? null) !== $mediaType
                || ($binding->integrity ?? null) !== $assetIntegrity
            ) {
                throw new \RuntimeException('The Studio redistribution PIN and browser manifest disagree.');
            }

            $bytes = self::fileBytes($root . '/browser/' . $path);
            $sha256 = hash('sha256', $bytes);
            $integrity = 'sha256-' . base64_encode(hash('sha256', $bytes, true));
            if (
                strlen($bytes) !== $assetBytes
                || !hash_equals($assetIntegrity, $integrity)
                || !is_string($binding->sha256 ?? null)
                || !hash_equals($binding->sha256, $sha256)
            ) {
                throw new \RuntimeException('The installed Studio redistribution bytes differ from their PIN.');
            }
        }
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
        if (
            $resolved === false
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
            if (
                !is_string($path)
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
                if (
                    !is_string($file)
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
            if (
                !is_array($before)
                || !self::regularFileStat($before)
                || !self::sameFileIdentity($pathStat, $before)
                || $before['size'] > self::MAX_RESOURCE_BYTES
            ) {
                throw new \RuntimeException('A Studio package file changed or exceeds its byte bound: ' . $path);
            }
            $bytes = stream_get_contents($handle, self::MAX_RESOURCE_BYTES + 1);
            $after = fstat($handle);
            if (
                !is_string($bytes)
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
        if (
            $relative === ''
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
