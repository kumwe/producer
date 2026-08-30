<?php

/**
 * Prove typed release metadata and manifest-only testkit resource access.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Schema\StudioBrowserAsset;
use Kumwe\Producer\Schema\StudioContractResources;
use Kumwe\Producer\Schema\StudioDocumentSchemaRegistry;
use Kumwe\Producer\Tests\TestCase;

final class StudioContractResourcesTest extends TestCase
{
    public function testReleaseCoordinatesAreExactTypedAndImmutable(): void
    {
        $release = StudioContractResources::releaseRecord();
        $this->assertSame($release, StudioContractResources::releaseRecord(), 'Release parsing must be shared.');
        $this->assertSame('0.1-draft', $release->contractVersion(), 'The release-record version must match.');
        $this->assertSame('0.1.0-beta.2', $release->release(), 'The coordinated release must match.');
        $this->assertSame('0.1.0-draft.2', $release->protocolVersion(), 'The protocol version must match.');
        $this->assertSame(
            'sha256-PupdZVv+htI0wd7bnFtwVdFzhYVB7DxTXh+viiBClNM=',
            $release->corpusManifestDigest(),
            'The corpus-manifest SRI must match the coordinated release.'
        );
        $this->assertSame([], $release->claimedProfiles(), 'Beta.2 must not invent conformance-profile claims.');
        $this->assertSame(8, count($release->packages()), 'Every coordinated package must remain pinned.');
        $this->assertSame(
            '38a96472ff4a5e1aa1fb92ed5451dc0fd112cf48',
            $release->sourceCommit(),
            'The npm-provenance-authenticated source commit must remain exact.'
        );
        $this->assertSame(8, count($release->packageIntegrities()), 'Every npm package must retain its integrity.');
        $this->assertSame(
            '19f8d64ff4d8cf7b8f637a9b8bfff739595d53e4e63cdbf45f6f95a01bade966',
            $release->recordSha256(),
            'The exact release-record bytes must stay pinned.'
        );
        $this->assertSame(false, $release->releaseReady(), 'The unpublished governed assets must block release.');
        $this->assertSame(
            [
                'The exact Studio beta.2 browser archive and detached checksum are locally reproduced and '
                    . 'fully verified, but the governed GitHub prerelease does not publish both assets yet.',
            ],
            $release->releaseBlockers(),
            'The exact unresolved publication proof must stay visible.'
        );

        $profiles = $release->claimedProfiles();
        $profiles[] = 'attacker/profile';
        $this->assertSame([], $release->claimedProfiles(), 'Returned profile lists must not mutate authority.');
        $packages = $release->packages();
        $packages['@attacker/package'] = '9.9.9';
        $this->assertSame(8, count($release->packages()), 'Returned package maps must not mutate authority.');
    }

    public function testBrowserArtifactsAreManifestAndDigestBound(): void
    {
        $release = StudioContractResources::releaseRecord();
        $locators = $release->browserArtifacts();
        $this->assertSame('studio-assets.json', $locators->manifestName(), 'The manifest locator must be exact.');
        $this->assertSame(
            'studio-browser-0.1.0-beta.2',
            $locators->authoringArchiveStem(),
            'The verified local candidate must retain its release-derived archive identity.'
        );
        $this->assertSame(
            '@kumwe/studio-renderer-web',
            $locators->enhancementPackage(),
            'The enhancement-runtime package must remain explicit.'
        );

        $manifest = StudioContractResources::browserManifestBytes();
        $this->assertSame(
            '89cd32a0e30075853d06056855c61f814be061b5a6fe2021b87d37db0c4fde68',
            hash('sha256', $manifest),
            'The public browser manifest bytes must match their package proof.'
        );
        $manifestDocument = json_decode($manifest, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($manifestDocument) || array_is_list($manifestDocument)) {
            throw new \RuntimeException('The browser manifest must decode to an object.');
        }
        $manifestAssets = is_array($manifestDocument['assets'] ?? null)
            ? $manifestDocument['assets']
            : [];
        $this->assertSame(73, count($manifestAssets), 'The exact outer-archive asset closure must remain fixed.');
        $roleCounts = [];
        foreach ($manifestAssets as $asset) {
            $role = is_array($asset) ? ($asset['role'] ?? null) : null;
            if (!is_string($role)) {
                throw new \RuntimeException('The browser manifest carries a malformed asset role.');
            }
            $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
        }
        ksort($roleCounts, SORT_STRING);
        $this->assertSame(
            [
                'browser-module' => 1,
                'documentation' => 32,
                'enhancement-runtime' => 1,
                'license' => 13,
                'notice' => 1,
                'release-record' => 1,
                'schema' => 24,
            ],
            $roleCounts,
            'The 74-member ustar proof must remain manifest plus the exact 73-role closure.'
        );
        $redistribution = array_values(array_filter(
            $manifestAssets,
            static fn (mixed $asset): bool => is_array($asset)
                && in_array($asset['role'] ?? null, ['license', 'notice'], true),
        ));
        $this->assertSame(
            [
                'LICENSE',
                'THIRD_PARTY_NOTICES.md',
                'third-party-licenses/codex-notifier-1.1.2.txt',
                'third-party-licenses/codex-tooltip-1.0.6.txt',
                'third-party-licenses/editorjs__caret-1.1.0.txt',
                'third-party-licenses/editorjs__dom-1.1.0.txt',
                'third-party-licenses/editorjs__editorjs-2.31.6.txt',
                'third-party-licenses/editorjs__helpers-1.2.2.txt',
                'third-party-licenses/lit__reactive-element-2.1.2.txt',
                'third-party-licenses/lit-3.3.3.txt',
                'third-party-licenses/lit-element-4.2.2.txt',
                'third-party-licenses/lit-html-3.3.3.txt',
                'third-party-licenses/lit-labs__ssr-dom-shim-1.6.0.txt',
                'third-party-licenses/types__trusted-types-2.0.7.txt',
            ],
            array_column($redistribution, 'path'),
            'Every Studio redistribution notice/license member must be preserved in manifest order.'
        );
        $this->assertSame(
            ['license' => 13, 'notice' => 1],
            array_count_values(array_column($redistribution, 'role')),
            'The complete Studio redistribution role closure must remain exact.'
        );
        $this->assertSame(
            29201,
            array_sum(array_column($redistribution, 'bytes')),
            'The redistributed notice/license byte envelope must remain exact.'
        );
        $browser = StudioContractResources::browserAsset('browser-module');
        $this->assertSame(940524, $browser->bytes(), 'The Studio authoring module byte count must be exact.');
        $this->assertSame(
            $browser->contentHash(),
            hash('sha256', StudioContractResources::browserAssetBytes('browser-module')),
            'The authoring module bytes must match their manifest content hash.'
        );
        $enhancement = StudioContractResources::browserAsset('enhancement-runtime');
        $this->assertSame(
            '@kumwe/studio-renderer-web',
            $enhancement->package(),
            'The enhancement asset must retain its npm package authority.'
        );
        $this->assertSame(
            'sha256-goxtMpJ4RXxU7s+kIsumMnL/XuGovlZUIOHFBgt0MVU=',
            $enhancement->integrity(),
            'The enhancement asset must retain its exact SRI.'
        );
        $this->assertThrows(
            static fn () => StudioContractResources::browserAsset('../unreleased'),
            \InvalidArgumentException::class,
            'The browser reader must expose only the closed role set.'
        );
        $this->assertThrows(
            static fn () => new StudioBrowserAsset(
                'browser-module',
                $browser->path(),
                '@kumwe/studio-renderer-web',
                $browser->bytes(),
                $browser->budgetBytes(),
                $browser->contentHash(),
                $browser->integrity(),
                true,
            ),
            \InvalidArgumentException::class,
            'A browser role cannot be relabelled as a different npm package.'
        );
    }

    public function testManifestBytesAndManifestedFixtureAreAvailableWithoutARoot(): void
    {
        $manifest = StudioContractResources::testkitManifestBytes();
        $digest = 'sha256-' . base64_encode(hash('sha256', $manifest, true));
        $this->assertSame(
            StudioContractResources::releaseRecord()->corpusManifestDigest(),
            $digest,
            'The byte reader must return the exact release-bound corpus manifest.'
        );

        $bytes = StudioContractResources::testkitBytes('fixtures/blueprint.product.example.json');
        $this->assertStringContains(
            '"kind": "blueprint"',
            $bytes,
            'The byte reader must select the exact Blueprint fixture.'
        );
    }

    public function testTraversalAndUnmanifestedResourceLookupsAreRefused(): void
    {
        $unsafe = [
            '',
            '/fixtures/blueprint.product.example.json',
            '../PIN.json',
            'fixtures/../PIN.json',
            'fixtures\\blueprint.product.example.json',
            "fixtures/blueprint\0.json",
            'fixtures//blueprint.product.example.json',
            'corpus-manifest.json',
            'fixtures/not-released.json',
        ];
        foreach ($unsafe as $relative) {
            $this->assertThrows(
                static fn () => StudioContractResources::testkitBytes($relative),
                \InvalidArgumentException::class,
                'The resource lookup must refuse ' . var_export($relative, true) . '.'
            );
        }
    }

    public function testPackageReadersRefuseLinksAndOversizedFilesBeforeUse(): void
    {
        $directory = sys_get_temp_dir() . '/producer-resource-reader-' . bin2hex(random_bytes(8));
        if (!mkdir($directory, 0700)) {
            throw new \RuntimeException('Could not create the isolated resource-reader test directory.');
        }
        $ordinary = $directory . '/ordinary.json';
        $oversized = $directory . '/oversized.json';
        $linked = $directory . '/linked.json';
        file_put_contents($ordinary, '{}');
        file_put_contents($oversized, str_repeat('x', 1048577));
        if (!symlink($ordinary, $linked)) {
            throw new \RuntimeException('Could not create the isolated resource-reader symlink.');
        }

        try {
            foreach ([StudioContractResources::class, StudioDocumentSchemaRegistry::class] as $class) {
                $reader = new \ReflectionMethod($class, 'fileBytes');
                $this->assertSame('{}', $reader->invoke(null, $ordinary), $class . ' must read a bounded file.');
                $this->assertThrows(
                    static fn () => $reader->invoke(null, $oversized),
                    \RuntimeException::class,
                    $class . ' must refuse a file beyond its fixed read cap.'
                );
                $this->assertThrows(
                    static fn () => $reader->invoke(null, $linked),
                    \RuntimeException::class,
                    $class . ' must refuse a symbolic-link resource.'
                );
            }
        } finally {
            @unlink($linked);
            @unlink($oversized);
            @unlink($ordinary);
            @rmdir($directory);
        }
    }
}
