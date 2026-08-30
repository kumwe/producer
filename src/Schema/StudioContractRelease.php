<?php

declare(strict_types=1);

namespace Kumwe\Producer\Schema;

/**
 * Immutable typed coordinates of Producer's exact coordinated Studio release.
 *
 * The value is constructed from release bytes already checked against
 * Producer's PIN and byte-identical protocol/testkit copies. Arrays use PHP
 * copy-on-write value semantics, so accessors cannot mutate the held profile
 * list or package map.
 *
 * @since   0.2.0
 */
final class StudioContractRelease
{
    /**
     * Exact public npm package family of the coordinated Studio release.
     *
     * @var list<string>
     *
     * @since 0.2.0
     */
    private const PACKAGE_NAMES = [
        '@kumwe/studio',
        '@kumwe/studio-core',
        '@kumwe/studio-media',
        '@kumwe/studio-preview',
        '@kumwe/studio-protocol',
        '@kumwe/studio-renderer-web',
        '@kumwe/studio-rich-text',
        '@kumwe/studio-testkit',
    ];

    /**
     * Hold one completely validated release coordinate.
     *
     * @param string                $contractVersion      Release-record contract version.
     * @param string                $release              Coordinated Studio Semantic Version.
     * @param string                $protocolVersion      Pinned protocol Semantic Version.
     * @param string                $corpusManifestDigest Canonical SHA-256 SRI of the testkit manifest.
     * @param list<string>          $claimedProfiles      Ordered unique qualified profile names.
     * @param array<string, string> $packages             Exact package name to Semantic Version map.
     * @param string                $sourceCommit         Provenance-authenticated source commit.
     * @param array<string, string> $packageIntegrities   Exact package name to npm SHA-512 integrity map.
     * @param StudioBrowserArtifacts $browserArtifacts    Closed browser artifact locators.
     * @param bool                  $releaseReady         Whether publication evidence is complete.
     * @param list<string>          $releaseBlockers      Reasons this generation cannot be released.
     * @param string                $recordSha256         Lowercase SHA-256 hex of the release bytes.
     *
     * @throws \InvalidArgumentException When any coordinate is malformed or repeated.
     *
     * @since   0.2.0
     */
    public function __construct(
        private readonly string $contractVersion,
        private readonly string $release,
        private readonly string $protocolVersion,
        private readonly string $corpusManifestDigest,
        private readonly array $claimedProfiles,
        private readonly array $packages,
        private readonly string $sourceCommit,
        private readonly array $packageIntegrities,
        private readonly StudioBrowserArtifacts $browserArtifacts,
        private readonly bool $releaseReady,
        private readonly array $releaseBlockers,
        private readonly string $recordSha256
    ) {
        if ($contractVersion === '' || strlen($contractVersion) > 100 || !mb_check_encoding($contractVersion, 'UTF-8')) {
            throw new \InvalidArgumentException('The Studio release contract version is malformed.');
        }
        if (
            !self::semanticVersion($release)
            || !self::semanticVersion($protocolVersion)
        ) {
            throw new \InvalidArgumentException('The Studio release or protocol version is not Semantic Versioning.');
        }
        if (preg_match('/^sha256-[A-Za-z0-9+\/]{42}[AEIMQUYcgkosw048]=$/', $corpusManifestDigest) !== 1) {
            throw new \InvalidArgumentException('The Studio corpus-manifest digest is not canonical SHA-256 SRI.');
        }
        if (!array_is_list($claimedProfiles)) {
            throw new \InvalidArgumentException('The Studio release must claim an ordered profile list.');
        }
        $seenProfiles = [];
        foreach ($claimedProfiles as $profile) {
            if (!is_string($profile) || !self::qualifiedName($profile) || isset($seenProfiles[$profile])) {
                throw new \InvalidArgumentException('The Studio release carries a malformed or repeated profile.');
            }
            $seenProfiles[$profile] = true;
        }
        if ($packages === [] || array_is_list($packages) || array_is_list($packageIntegrities)) {
            throw new \InvalidArgumentException('The Studio release must carry named package coordinates.');
        }
        foreach ($packages as $package => $version) {
            if (
                !is_string($package)
                || preg_match('/^@kumwe\/[a-z][a-z0-9-]*$/', $package) !== 1
                || !is_string($version)
                || !self::semanticVersion($version)
                || $version !== $release
            ) {
                throw new \InvalidArgumentException('The Studio release carries a malformed package coordinate.');
            }
        }
        $names = array_keys($packages);
        sort($names, SORT_STRING);
        if ($names !== self::PACKAGE_NAMES) {
            throw new \InvalidArgumentException('The Studio release must carry the exact eight-package family.');
        }
        $integrityNames = array_keys($packageIntegrities);
        sort($integrityNames, SORT_STRING);
        if ($integrityNames !== self::PACKAGE_NAMES) {
            throw new \InvalidArgumentException('The Studio release must bind every package integrity.');
        }
        foreach ($packageIntegrities as $integrity) {
            if (
                !is_string($integrity)
                || preg_match('/^sha512-[A-Za-z0-9+\/]+={0,2}$/', $integrity) !== 1
            ) {
                throw new \InvalidArgumentException('The Studio release carries a malformed package integrity.');
            }
        }
        if (preg_match('/^[a-f0-9]{40}$/', $sourceCommit) !== 1) {
            throw new \InvalidArgumentException('The Studio release source commit is malformed.');
        }
        if (!array_is_list($releaseBlockers)) {
            throw new \InvalidArgumentException('The Studio release blockers must be an ordered list.');
        }
        foreach ($releaseBlockers as $blocker) {
            if (!is_string($blocker) || $blocker === '' || !mb_check_encoding($blocker, 'UTF-8')) {
                throw new \InvalidArgumentException('The Studio release carries a malformed blocker.');
            }
        }
        if ($releaseReady === ($releaseBlockers !== [])) {
            throw new \InvalidArgumentException('Studio release readiness and blockers disagree.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $recordSha256) !== 1) {
            throw new \InvalidArgumentException('The Studio release-record SHA-256 is malformed.');
        }
    }

    /**
     * Release-record grammar version.
     *
     * @since   0.2.0
     */
    public function contractVersion(): string
    {
        return $this->contractVersion;
    }

    /**
     * Coordinated Studio release Semantic Version.
     *
     * @since   0.2.0
     */
    public function release(): string
    {
        return $this->release;
    }

    /**
     * Studio protocol Semantic Version pinned by this release.
     *
     * @since   0.2.0
     */
    public function protocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * Canonical SHA-256 SRI digest of the exact testkit corpus manifest.
     *
     * @since   0.2.0
     */
    public function corpusManifestDigest(): string
    {
        return $this->corpusManifestDigest;
    }

    /**
     * Ordered exact profile claims of the coordinated release.
     *
     * @return list<string>
     *
     * @since   0.2.0
     */
    public function claimedProfiles(): array
    {
        return $this->claimedProfiles;
    }

    /**
     * Exact coordinated JavaScript package versions, keyed by package name.
     *
     * @return array<string, string>
     *
     * @since   0.2.0
     */
    public function packages(): array
    {
        return $this->packages;
    }

    /**
     * Exact Git commit authenticated by the npm provenance attestations.
     *
     * @since 0.2.0
     */
    public function sourceCommit(): string
    {
        return $this->sourceCommit;
    }

    /**
     * Exact registry SHA-512 integrity for every coordinated npm package.
     *
     * @return array<string, string>
     *
     * @since 0.2.0
     */
    public function packageIntegrities(): array
    {
        return $this->packageIntegrities;
    }

    /** @since 0.2.0 */
    public function browserArtifacts(): StudioBrowserArtifacts
    {
        return $this->browserArtifacts;
    }

    /** @since 0.2.0 */
    public function releaseReady(): bool
    {
        return $this->releaseReady;
    }

    /**
     * Exact blockers preventing a Producer release for this generation.
     *
     * @return list<string>
     *
     * @since 0.2.0
     */
    public function releaseBlockers(): array
    {
        return $this->releaseBlockers;
    }

    /**
     * Lowercase SHA-256 hex of the exact installed release-record bytes.
     *
     * @since   0.2.0
     */
    public function recordSha256(): string
    {
        return $this->recordSha256;
    }

    /**
     * Whether a coordinate satisfies the pinned Semantic Version grammar.
     *
     * @param string $value Candidate version.
     *
     * @since   0.2.0
     */
    private static function semanticVersion(string $value): bool
    {
        return strlen($value) <= 100
            && preg_match(
                '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)'
                    . '(?:-(?:0|[1-9][0-9]*|[0-9]*[A-Za-z-][0-9A-Za-z-]*)'
                    . '(?:\.(?:0|[1-9][0-9]*|[0-9]*[A-Za-z-][0-9A-Za-z-]*))*)?'
                    . '(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?\z/',
                $value
            ) === 1;
    }

    /**
     * Whether a profile uses the pinned qualified-name grammar.
     *
     * @param string $value Candidate profile name.
     *
     * @since   0.2.0
     */
    private static function qualifiedName(string $value): bool
    {
        return strlen($value) <= 160
            && preg_match(
                '/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*\/[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*\z/',
                $value
            ) === 1;
    }
}
