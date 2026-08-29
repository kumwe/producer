<?php

/**
 * Public API compatibility-pin tests.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Tests\TestCase;

final class PublicApiManifestTest extends TestCase
{
    /**
     * The checked-in manifest records the complete 0.2.0 surface.
     *
     * @since 0.2.0
     */
    public function testManifestCoordinatesAndMembersArePinned(): void
    {
        $path = dirname(__DIR__, 2) . '/resources/public-api.json';
        $decoded = json_decode((string) file_get_contents($path), true);

        $this->assertTrue(is_array($decoded), 'The API manifest must decode as an object.');
        if (!is_array($decoded)) {
            throw new \RuntimeException('The API manifest did not decode as an object.');
        }
        $this->assertSame(2, $decoded['schema'] ?? null, 'The signature-aware schema must be pinned.');
        $this->assertSame('kumwe/producer', $decoded['package'] ?? null, 'The package identity must be pinned.');
        $this->assertSame('0.2.0', $decoded['release'] ?? null, 'The reviewed release surface must be pinned.');
        $this->assertSame(
            'Kumwe\\Producer\\',
            $decoded['namespace'] ?? null,
            'The canonical namespace must be pinned.',
        );

        $types = $decoded['types'] ?? null;
        $this->assertTrue(is_array($types), 'The manifest must contain a canonical type map.');
        if (!is_array($types)) {
            throw new \RuntimeException('The API manifest has no canonical type map.');
        }
        $this->assertSame(63, count($types), 'Exactly the reviewed Producer public types must ship.');
        $response = $types['Kumwe\\Producer\\Wire\\Response'] ?? null;
        $this->assertTrue(
            is_array($response)
                && is_array($response['methods'] ?? null)
                && is_array($response['methods']['__construct'] ?? null)
                && is_array($response['methods']['__construct']['parameters'] ?? null),
            'Public constructor signatures must be compatibility-pinned.',
        );
        $policy = $types['Kumwe\\Producer\\Render\\RenderPolicy'] ?? null;
        $this->assertTrue(
            is_array($policy)
                && is_array($policy['enum'] ?? null)
                && is_array($policy['enum']['cases'] ?? null),
            'Public enum cases must be compatibility-pinned.',
        );
    }

    /**
     * The verifier must pass the current pin and reject deliberate member drift.
     *
     * @since 0.2.0
     */
    public function testVerifierAndNegativeMemberDriftSelfTest(): void
    {
        $root = dirname(__DIR__, 2);
        $php = escapeshellarg(PHP_BINARY);
        $tool = escapeshellarg($root . '/tools/verify-api.php');

        $verifyOutput = [];
        $verifyStatus = 0;
        exec("{$php} {$tool} 2>&1", $verifyOutput, $verifyStatus);
        $this->assertSame(0, $verifyStatus, 'The current public API pin must verify.');
        $this->assertStringContains(
            'all public members are pinned',
            implode("\n", $verifyOutput),
            'The verifier must report member-level coverage.',
        );

        $negativeOutput = [];
        $negativeStatus = 0;
        exec("{$php} {$tool} --self-test 2>&1", $negativeOutput, $negativeStatus);
        $this->assertSame(0, $negativeStatus, 'The negative member-drift self-test must pass.');
        $this->assertStringContains(
            'member drift',
            implode("\n", $negativeOutput),
            'The self-test must prove that method signature drift is rejected.',
        );
    }
}
