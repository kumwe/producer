<?php

/**
 * Prove the two published Studio browser policies are emitted exactly, widened
 * only by exact origins, and refuse weak nonces and inadmissible sources.
 * @since 0.3.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Deployment\DeploymentException;
use Kumwe\Producer\Deployment\StudioContentSecurityPolicy;
use Kumwe\Producer\Schema\StudioContractResources;
use Kumwe\Producer\Tests\TestCase;

final class ContentSecurityPolicyTest extends TestCase
{
    public function testTheAuthoringPolicyIsTheManifestTemplateWithANonceAndExactOrigins(): void
    {
        $manifest = CanonicalJson::decode(StudioContractResources::browserManifestBytes());
        $this->assertTrue($manifest instanceof \stdClass, 'The manifest decodes to an object.');
        $template = $manifest->contentSecurityPolicy->headerTemplate;
        $nonce = base64_encode(str_repeat("\x01", 16));

        $policy = StudioContentSecurityPolicy::authoring($nonce);
        $this->assertSame(str_replace('{{STYLE_NONCE}}', $nonce, $template), $policy, 'Same-origin: the template verbatim.');
        $this->assertStringExcludes('{{STYLE_NONCE}}', $policy, 'The placeholder is replaced.');

        $widened = StudioContentSecurityPolicy::authoring($nonce, ['https://cdn.jsdelivr.net', 'https://cdn.jsdelivr.net']);
        $this->assertStringContains("script-src 'self' https://cdn.jsdelivr.net;", $widened, 'One exact origin joins script-src.');
        $this->assertSame(
            substr_count($widened, 'https://cdn.jsdelivr.net'),
            1,
            'A repeated origin is admitted once and appears in script-src only.'
        );
        $this->assertStringContains("connect-src 'self';", $widened, 'connect-src stays same-origin.');
    }

    public function testTheEnhancementPolicyIsTheManifestBaselineWithExactOrigins(): void
    {
        $manifest = CanonicalJson::decode(StudioContractResources::browserManifestBytes());
        $this->assertTrue($manifest instanceof \stdClass, 'The manifest decodes to an object.');
        $baseline = $manifest->enhancementRuntime->contentSecurityPolicy;
        $this->assertSame($baseline, StudioContentSecurityPolicy::enhancement(), 'Same-origin: the baseline verbatim.');
        $this->assertSame(
            str_replace("script-src 'self'", "script-src 'self' http://localhost:8080", $baseline),
            StudioContentSecurityPolicy::enhancement(['http://localhost:8080']),
            'A loopback development origin is admitted exactly.'
        );
    }

    public function testWeakNoncesAndInadmissibleOriginsAreRefused(): void
    {
        foreach (['short', base64_encode(str_repeat("\x01", 15)), 'not base64 at all!!', ''] as $nonce) {
            $refusal = $this->assertThrows(
                static fn () => StudioContentSecurityPolicy::authoring($nonce),
                DeploymentException::class,
                'Nonce ' . json_encode($nonce) . ' must be refused.'
            );
            $this->assertTrue($refusal instanceof DeploymentException, 'Typed refusal.');
            $this->assertSame('nonce', $refusal->rejection(), 'The refusal names the nonce rule.');
        }
        foreach (
            [
                'https://*.jsdelivr.net',
                'https://cdn.jsdelivr.net/npm',
                'http://cdn.example',
                'https://user@cdn.example',
                'https://cdn.example?x',
                "'unsafe-inline'",
                'https:',
                '*',
            ] as $origin
        ) {
            $refusal = $this->assertThrows(
                static fn () => StudioContentSecurityPolicy::enhancement([$origin]),
                DeploymentException::class,
                'Origin ' . json_encode($origin) . ' must be refused.'
            );
            $this->assertTrue($refusal instanceof DeploymentException, 'Typed refusal.');
            $this->assertSame('origin', $refusal->rejection(), 'The refusal names the origin rule.');
        }
    }
}
