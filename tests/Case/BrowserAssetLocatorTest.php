<?php

/**
 * Prove released Studio assets resolve to integrity-bound locations in both
 * served layouts and that inadmissible bases are refused before use.
 * @since 0.3.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Deployment\DeploymentException;
use Kumwe\Producer\Deployment\StudioBrowserAssetLocator;
use Kumwe\Producer\Schema\StudioContractResources;
use Kumwe\Producer\Tests\TestCase;

final class BrowserAssetLocatorTest extends TestCase
{
    public function testTheNpmPackageLayoutBindsPackageVersionPathAndIntegrity(): void
    {
        $release = StudioContractResources::releaseRecord();
        $locator = StudioBrowserAssetLocator::npmPackages('https://cdn.jsdelivr.net/npm/');
        $this->assertSame('https://cdn.jsdelivr.net/npm', $locator->baseUrl(), 'A trailing slash is normalized away.');
        $this->assertSame('https://cdn.jsdelivr.net', $locator->origin(), 'The CSP origin is the exact scheme and host.');

        foreach (['browser-module' => '@kumwe/studio', 'enhancement-runtime' => '@kumwe/studio-renderer-web'] as $role => $package) {
            $asset = StudioContractResources::browserAsset($role);
            $location = $locator->locate($role);
            $this->assertSame(
                'https://cdn.jsdelivr.net/npm/' . $package . '@' . $release->packages()[$package] . '/dist/browser/' . $asset->path(),
                $location->url(),
                $role . ' resolves inside its exact published package version.'
            );
            $this->assertSame($asset->integrity(), $location->integrity(), $role . ' carries the manifest SRI value.');
            $this->assertSame($asset->packagePath(), 'dist/browser/' . $asset->path(), 'The package path is pin-bound.');
            $this->assertSame($role, $location->role(), 'The role travels with the location.');
        }
        $module = $locator->locate('browser-module')->scriptElement();
        $this->assertStringContains('<script type="module" src="https://cdn.jsdelivr.net/npm/@kumwe/studio@', $module, 'ESM.');
        $this->assertStringContains('integrity="sha256-', $module, 'The module element is integrity-checked.');
        $this->assertStringContains('crossorigin="anonymous"></script>', $module, 'Cross-origin SRI needs CORS mode.');
        $runtime = $locator->locate('enhancement-runtime')->scriptElement();
        $this->assertStringExcludes('type="module"', $runtime, 'The enhancement runtime is a classic IIFE.');
        $this->assertStringContains('crossorigin="anonymous" defer></script>', $runtime, 'The runtime is deferred.');
    }

    public function testTheReleaseDirectoryLayoutServesSameOriginWithoutAnOrigin(): void
    {
        $locator = StudioBrowserAssetLocator::releaseDirectory('/assets/studio-browser/');
        $asset = StudioContractResources::browserAsset('browser-module');
        $location = $locator->locate('browser-module');
        $this->assertSame('/assets/studio-browser/' . $asset->path(), $location->url(), 'Directory layout is base/path.');
        $this->assertSame(null, $location->origin(), 'A same-origin base widens no CSP origin.');

        $mirror = StudioBrowserAssetLocator::releaseDirectory('http://localhost:8080/studio');
        $this->assertSame('http://localhost:8080', $mirror->origin(), 'Loopback HTTP is admitted for development.');
        $this->assertSame(
            'http://localhost:8080/studio/' . $asset->path(),
            $mirror->locate('browser-module')->url(),
            'The loopback base keeps its path.'
        );
    }

    public function testInadmissibleBasesAndRolesAreRefused(): void
    {
        foreach (
            [
                '',
                'cdn.jsdelivr.net/npm',
                'http://cdn.example/npm',
                'https://user:secret@cdn.example/npm',
                'https://cdn.example/npm?x=1',
                'https://cdn.example/npm#fragment',
                'https://cdn.example/../npm',
                'https://*.example/npm',
                '//cdn.example/npm',
                '/assets/../studio',
                "/assets/studio\n",
            ] as $base
        ) {
            $refusal = $this->assertThrows(
                static fn () => StudioBrowserAssetLocator::npmPackages($base),
                DeploymentException::class,
                'Base ' . json_encode($base) . ' must be refused.'
            );
            $this->assertTrue($refusal instanceof DeploymentException, 'Typed refusal.');
            $this->assertSame('base-url', $refusal->rejection(), 'The refusal names the base rule.');
        }
        $refusal = $this->assertThrows(
            static fn () => StudioBrowserAssetLocator::npmPackages('https://cdn.jsdelivr.net/npm')->locate('documentation'),
            DeploymentException::class,
            'Only the two executable roles are locatable.'
        );
        $this->assertTrue($refusal instanceof DeploymentException, 'Typed refusal.');
        $this->assertSame('unknown-asset', $refusal->rejection(), 'The refusal names the unknown role.');
    }
}
