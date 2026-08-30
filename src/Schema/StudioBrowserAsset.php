<?php

declare(strict_types=1);

namespace Kumwe\Producer\Schema;

/**
 * One manifest-verified, package-provenanced Studio browser asset.
 *
 * @since 0.2.0
 */
final class StudioBrowserAsset
{
    /**
     * @param string $role        Closed executable asset role.
     * @param string $path        Manifest-relative content-addressed path.
     * @param string $package     Public npm package owning the bytes.
     * @param int    $bytes       Exact asset byte count.
     * @param int    $budgetBytes Maximum released byte budget.
     * @param string $contentHash Lowercase SHA-256 content hash.
     * @param string $integrity   Canonical SHA-256 SRI spelling.
     * @param bool   $minified    Required production-minification marker.
     *
     * @throws \InvalidArgumentException When metadata is not an exact executable asset record.
     *
     * @since 0.2.0
     */
    public function __construct(
        private readonly string $role,
        private readonly string $path,
        private readonly string $package,
        private readonly int $bytes,
        private readonly int $budgetBytes,
        private readonly string $contentHash,
        private readonly string $integrity,
        private readonly bool $minified,
    ) {
        if (
            !in_array($role, ['browser-module', 'enhancement-runtime'], true)
            || ($role === 'browser-module'
                && ($package !== '@kumwe/studio'
                    || preg_match('~^assets/studio-browser-[a-f0-9]{16}\.min\.js$~', $path) !== 1))
            || ($role === 'enhancement-runtime'
                && ($package !== '@kumwe/studio-renderer-web'
                    || preg_match('~^assets/studio-enhancements-[a-f0-9]{16}\.min\.js$~', $path) !== 1))
            || $bytes < 1
            || $budgetBytes < $bytes
            || preg_match('/^[a-f0-9]{64}$/', $contentHash) !== 1
            || preg_match('/^sha256-[A-Za-z0-9+\/]{42}[AEIMQUYcgkosw048]=$/', $integrity) !== 1
            || !str_contains($path, substr($contentHash, 0, 16))
            || !$minified
        ) {
            throw new \InvalidArgumentException('Studio browser asset metadata is malformed.');
        }
    }

    /** @since 0.2.0 */
    public function role(): string
    {
        return $this->role;
    }

    /** @since 0.2.0 */
    public function path(): string
    {
        return $this->path;
    }

    /** @since 0.2.0 */
    public function package(): string
    {
        return $this->package;
    }

    /** @since 0.2.0 */
    public function bytes(): int
    {
        return $this->bytes;
    }

    /** @since 0.2.0 */
    public function budgetBytes(): int
    {
        return $this->budgetBytes;
    }

    /** @since 0.2.0 */
    public function contentHash(): string
    {
        return $this->contentHash;
    }

    /** @since 0.2.0 */
    public function integrity(): string
    {
        return $this->integrity;
    }

    /** @since 0.2.0 */
    public function minified(): bool
    {
        return $this->minified;
    }
}
