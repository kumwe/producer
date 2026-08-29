<?php

/**
 * Prove typed release metadata and manifest-only testkit resource access.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

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
        $this->assertSame('0.1.0-rc.1', $release->release(), 'The coordinated release must match.');
        $this->assertSame('0.1.0-draft.2', $release->protocolVersion(), 'The protocol version must match.');
        $this->assertSame(
            'sha256-4/ChS3pCA32CoZ+BjvL2tj2RGOFJNdqXwuqO8gWDxTs=',
            $release->corpusManifestDigest(),
            'The corpus-manifest SRI must match the coordinated release.'
        );
        $this->assertSame(9, count($release->claimedProfiles()), 'Every coordinated profile must remain claimed.');
        $this->assertSame(8, count($release->packages()), 'Every coordinated package must remain pinned.');
        $this->assertSame(
            'b891bf573cfac52c751c1f13fa372a4f63ea76795316cce6f881b577f08bce56',
            $release->recordSha256(),
            'The exact release-record bytes must stay pinned.'
        );

        $profiles = $release->claimedProfiles();
        $profiles[] = 'attacker/profile';
        $this->assertSame(9, count($release->claimedProfiles()), 'Returned profile lists must not mutate authority.');
        $packages = $release->packages();
        $packages['@attacker/package'] = '9.9.9';
        $this->assertSame(8, count($release->packages()), 'Returned package maps must not mutate authority.');
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
