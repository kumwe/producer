<?php

declare(strict_types=1);

namespace Kumwe\Producer\Deployment;

use Kumwe\Producer\Schema\StudioContractResources;

/**
 * Resolve where a page loads the pinned Studio browser assets from.
 * Producer knows the exact released bytes — package, package-relative path,
 * byte count and SRI value — but never serves them; the host decides the
 * origin. Two layouts are supported: the npm package layout a registry CDN
 * (for example `https://cdn.jsdelivr.net/npm`) or a self-hosted package
 * mirror exposes as `<base>/<package>@<version>/<packagePath>`, and the
 * release-directory layout of the extracted governed archive or of a
 * `dist/browser` copy served as `<base>/<path>`. Every location carries the
 * manifest SRI value, so a mirror or CDN that serves different bytes fails
 * in the browser rather than running.
 * @since   0.3.0
 */
final class StudioBrowserAssetLocator
{
    /**
     * Registry CDN or package-mirror layout.
     * @since   0.3.0
     */
    private const LAYOUT_NPM_PACKAGES = 'npm-packages';

    /**
     * Extracted release-archive or `dist/browser` directory layout.
     * @since   0.3.0
     */
    private const LAYOUT_RELEASE_DIRECTORY = 'release-directory';

    /**
     * Loopback hosts that may be served over plain HTTP during development.
     * @var list<string>
     * @since   0.3.0
     */
    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    /**
     * Bind one normalized base and layout; construct through the named factories.
     * @param string      $layout  One of the two closed layouts.
     * @param string      $baseUrl Normalized absolute URL or site-absolute path without a
     *                             trailing slash.
     * @param string|null $origin  Exact scheme://host[:port], or null for same-origin.
     * @since   0.3.0
     */
    private function __construct(
        private readonly string $layout,
        private readonly string $baseUrl,
        private readonly ?string $origin,
    ) {
    }

    /**
     * Locate assets inside published npm packages: `<base>/<package>@<version>/<packagePath>`.
     * `https://cdn.jsdelivr.net/npm` is the public registry CDN layout; a
     * self-hosted mirror that keeps the same directory shape works unchanged.
     * @param string $baseUrl Absolute `https://` URL (plain HTTP only on a loopback
     *                        host) or a site-absolute path, without query, fragment
     *                        or user information.
     * @throws DeploymentException `base-url` when the base is not an admissible location.
     * @since   0.3.0
     */
    public static function npmPackages(string $baseUrl): self
    {
        [$normalized, $origin] = self::normalize($baseUrl);

        return new self(self::LAYOUT_NPM_PACKAGES, $normalized, $origin);
    }

    /**
     * Locate assets inside one served release directory: `<base>/<path>`.
     * This is the layout of the governed `studio-browser-<release>` archive
     * once extracted, and of a package's `dist/browser` directory served as is.
     * @param string $baseUrl Absolute `https://` URL (plain HTTP only on a loopback
     *                        host) or a site-absolute path, without query, fragment
     *                        or user information.
     * @throws DeploymentException `base-url` when the base is not an admissible location.
     * @since   0.3.0
     */
    public static function releaseDirectory(string $baseUrl): self
    {
        [$normalized, $origin] = self::normalize($baseUrl);

        return new self(self::LAYOUT_RELEASE_DIRECTORY, $normalized, $origin);
    }

    /**
     * The normalized base every located URL starts with.
     * @since   0.3.0
     */
    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * The exact origin a `script-src` directive must admit, or null when the
     * assets are served from the page's own origin.
     * @since   0.3.0
     */
    public function origin(): ?string
    {
        return $this->origin;
    }

    /**
     * Resolve one released asset by its closed role against the pinned release.
     * @param string $role `browser-module` or `enhancement-runtime`.
     * @throws DeploymentException `unknown-asset` when the role is not a released runtime asset.
     * @throws \RuntimeException    When the installed Studio contract bytes are missing or drifted.
     * @since   0.3.0
     */
    public function locate(string $role): StudioBrowserAssetLocation
    {
        try {
            $asset = StudioContractResources::browserAsset($role);
        } catch (\InvalidArgumentException $error) {
            throw new DeploymentException('unknown-asset', $error->getMessage());
        }
        $version = StudioContractResources::releaseRecord()->packages()[$asset->package()] ?? null;
        if (!is_string($version)) {
            throw new \RuntimeException('The pinned Studio release does not version the asset package.');
        }
        $url = $this->layout === self::LAYOUT_NPM_PACKAGES
            ? $this->baseUrl . '/' . $asset->package() . '@' . $version . '/' . $asset->packagePath()
            : $this->baseUrl . '/' . $asset->path();

        return new StudioBrowserAssetLocation($asset->role(), $url, $asset->integrity(), $this->origin);
    }

    /**
     * Admit one base location and derive its CSP origin.
     * @param string $baseUrl Caller-supplied base.
     * @return array{0: string, 1: string|null} Normalized base and its origin.
     * @throws DeploymentException `base-url` when the base is empty, relative, carries user
     *                             information, a query, a fragment, a traversing segment,
     *                             a wildcard or an inadmissible scheme.
     * @since   0.3.0
     */
    private static function normalize(string $baseUrl): array
    {
        $trimmed = rtrim($baseUrl, '/');
        if ($trimmed === '' || str_contains($trimmed, '*') || preg_match('/[\x00-\x20\x7F"\'<>\\\\]/', $trimmed) === 1) {
            throw new DeploymentException('base-url', 'A Studio asset base must be a non-empty, wildcard-free location.');
        }
        if (str_starts_with($trimmed, '/')) {
            if (str_starts_with($trimmed, '//') || !self::safePath($trimmed)) {
                throw new DeploymentException('base-url', 'A same-origin Studio asset base must be a plain absolute path.');
            }

            return [$trimmed, null];
        }

        $parts = parse_url($trimmed);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;
        $path = is_array($parts) ? ($parts['path'] ?? '') : '';
        if (
            !is_array($parts)
            || !is_string($scheme)
            || !is_string($host)
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || !is_string($path)
            || ($path !== '' && !self::safePath($path))
        ) {
            throw new DeploymentException('base-url', 'A Studio asset base must be an absolute origin with an optional path.');
        }
        $scheme = strtolower($scheme);
        $host = strtolower($host);
        if ($scheme !== 'https' && ($scheme !== 'http' || !in_array($host, self::LOOPBACK_HOSTS, true))) {
            throw new DeploymentException('base-url', 'Studio assets load over HTTPS, or plain HTTP only from a loopback host.');
        }
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $origin = $scheme . '://' . $host . $port;

        return [$origin . $path, $origin];
    }

    /**
     * Accept only a plain absolute path with no empty, dot or dot-dot segment.
     * @param string $path Candidate path.
     * @since   0.3.0
     */
    private static function safePath(string $path): bool
    {
        if (preg_match('#^(?:/[A-Za-z0-9._~!$&\'()*+,;=:@%-]+)+$#', $path) !== 1) {
            return false;
        }
        foreach (explode('/', substr($path, 1)) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
