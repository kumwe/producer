<?php

declare(strict_types=1);

namespace Kumwe\Producer\Deployment;

/**
 * One resolved, integrity-bound URL for a released Studio browser asset.
 * The value pairs the exact URL a page loads with the manifest's SRI value,
 * so the host emits a script element that the browser refuses to execute when
 * the served bytes differ from the pinned release, wherever they are served
 * from — the host origin, a mirror or a public npm CDN.
 * @since   0.3.0
 */
final class StudioBrowserAssetLocation
{
    /**
     * Bind one located asset; construct only through {@see StudioBrowserAssetLocator}.
     * @param string      $role      `browser-module` or `enhancement-runtime`.
     * @param string      $url       Absolute URL or site-absolute path of the asset.
     * @param string      $integrity Canonical SHA-256 SRI value from the asset manifest.
     * @param string|null $origin    Exact scheme://host[:port] serving the asset, or
     *                               null when it is served same-origin.
     * @since   0.3.0
     */
    public function __construct(
        private readonly string $role,
        private readonly string $url,
        private readonly string $integrity,
        private readonly ?string $origin,
    ) {
    }

    /**
     * The closed asset role: `browser-module` or `enhancement-runtime`.
     * @since   0.3.0
     */
    public function role(): string
    {
        return $this->role;
    }

    /**
     * The URL a page loads the asset from.
     * @since   0.3.0
     */
    public function url(): string
    {
        return $this->url;
    }

    /**
     * The manifest SRI value the script element carries.
     * @since   0.3.0
     */
    public function integrity(): string
    {
        return $this->integrity;
    }

    /**
     * The exact origin a Content-Security-Policy `script-src` must admit, or
     * null when the asset is served from the page's own origin.
     * @since   0.3.0
     */
    public function origin(): ?string
    {
        return $this->origin;
    }

    /**
     * The exact script element for this asset: an integrity-checked ES module
     * for the authoring bundle (which mounts every `[data-kumwe-studio]`
     * target after it loads) or a deferred, integrity-checked classic script
     * for the published-page enhancement runtime.
     * @since   0.3.0
     */
    public function scriptElement(): string
    {
        $attributes = sprintf(
            'src="%s" integrity="%s" crossorigin="anonymous"',
            self::attribute($this->url),
            self::attribute($this->integrity),
        );

        return $this->role === 'browser-module'
            ? '<script type="module" ' . $attributes . '></script>'
            : '<script ' . $attributes . ' defer></script>';
    }

    /**
     * Escape one attribute value for a double-quoted HTML attribute.
     * @param string $value Attribute text.
     * @since   0.3.0
     */
    private static function attribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
