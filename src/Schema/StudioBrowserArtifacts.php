<?php

declare(strict_types=1);

namespace Kumwe\Producer\Schema;

/**
 * Immutable locators for one coordinated Studio browser generation.
 *
 * Exact filenames and bytes remain authoritative in studio-assets.json;
 * this value is the closed release-record path from a Studio coordinate to
 * that manifest, the authoring archive, and renderer-web runtime package.
 *
 * @since 0.2.0
 */
final class StudioBrowserArtifacts
{
    /**
     * @param string $release                    Coordinated Studio Semantic Version.
     * @param string $manifestName               Package-relative browser manifest name.
     * @param string $manifestSchema             Canonical browser-manifest schema id.
     * @param string $authoringArchiveStem       Release-derived outer archive stem.
     * @param string $authoringAssetRole         Browser-module role selected by the archive.
     * @param string $authoringLoading           Browser-module loading mode.
     * @param string $enhancementAssetRole       Public enhancement-runtime role.
     * @param string $enhancementLoading         Enhancement-runtime loading mode.
     * @param string $enhancementPackage         Package owning the enhancement bytes.
     * @param string $enhancementPackageBasePath Package-relative browser asset directory.
     *
     * @throws \InvalidArgumentException When any locator differs from the Studio contract.
     *
     * @since 0.2.0
     */
    public function __construct(
        private readonly string $release,
        private readonly string $manifestName,
        private readonly string $manifestSchema,
        private readonly string $authoringArchiveStem,
        private readonly string $authoringAssetRole,
        private readonly string $authoringLoading,
        private readonly string $enhancementAssetRole,
        private readonly string $enhancementLoading,
        private readonly string $enhancementPackage,
        private readonly string $enhancementPackageBasePath,
    ) {
        if (
            $manifestName !== 'studio-assets.json'
            || $manifestSchema !== 'https://schemas.kumwe.org/studio/v1/studio-browser-assets.schema.json'
            || $authoringArchiveStem !== 'studio-browser-' . $release
            || $authoringAssetRole !== 'browser-module'
            || $authoringLoading !== 'module'
            || $enhancementAssetRole !== 'enhancement-runtime'
            || $enhancementLoading !== 'defer'
            || $enhancementPackage !== '@kumwe/studio-renderer-web'
            || $enhancementPackageBasePath !== 'dist/browser/'
        ) {
            throw new \InvalidArgumentException('Studio browser artifact locators are malformed or incomplete.');
        }
    }

    /** @since 0.2.0 */
    public function manifestName(): string
    {
        return $this->manifestName;
    }

    /** @since 0.2.0 */
    public function manifestSchema(): string
    {
        return $this->manifestSchema;
    }

    /** @since 0.2.0 */
    public function authoringArchiveStem(): string
    {
        return $this->authoringArchiveStem;
    }

    /** @since 0.2.0 */
    public function authoringAssetRole(): string
    {
        return $this->authoringAssetRole;
    }

    /** @since 0.2.0 */
    public function authoringLoading(): string
    {
        return $this->authoringLoading;
    }

    /** @since 0.2.0 */
    public function enhancementAssetRole(): string
    {
        return $this->enhancementAssetRole;
    }

    /** @since 0.2.0 */
    public function enhancementLoading(): string
    {
        return $this->enhancementLoading;
    }

    /** @since 0.2.0 */
    public function enhancementPackage(): string
    {
        return $this->enhancementPackage;
    }

    /** @since 0.2.0 */
    public function enhancementPackageBasePath(): string
    {
        return $this->enhancementPackageBasePath;
    }
}
