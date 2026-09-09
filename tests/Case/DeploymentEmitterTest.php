<?php

/**
 * Prove the inert deployment pair is emitted only for a schema-valid,
 * release-bound, self-selecting document and that every refusal is typed.
 * @since 0.3.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Deployment\DeploymentException;
use Kumwe\Producer\Deployment\StudioDeploymentEmitter;
use Kumwe\Producer\Schema\StudioContractResources;
use Kumwe\Producer\Schema\StudioDocumentSchemaRegistry;
use Kumwe\Producer\Tests\TestCase;

final class DeploymentEmitterTest extends TestCase
{
    public function testAHostedDeploymentRendersAnInertEscapedDiscoveryPair(): void
    {
        $emitter = self::emitter();
        $document = self::hosted();
        $document->session->actor->displayName = 'Ex </script><b>&' . "\u{2028}" . "\u{2029}";
        $html = $emitter->render('article-studio', 'article-studio-config', $document);

        $this->assertStringContains(
            '<div id="article-studio" data-kumwe-studio="article-studio-config"></div>' . "\n"
            . '<script id="article-studio-config" type="application/json">',
            $html,
            'The pair must associate the target with its configuration block.'
        );
        $this->assertStringExcludes('</script><b>', $html, 'A stored value must not close the JSON block.');
        $this->assertStringContains(
            '\\u003c/script\\u003e\\u003cb\\u003e\\u0026\\u2028\\u2029',
            $html,
            'Markup-significant and line-terminator characters are escaped as JSON.'
        );
        $this->assertTrue(preg_match('#>(\{.*\})</script>$#s', $html, $match) === 1, 'The block ends the pair.');
        $decoded = json_decode($match[1] ?? '', false, 32, JSON_THROW_ON_ERROR);
        $this->assertSame(
            $document->session->actor->displayName,
            $decoded->session->actor->displayName,
            'The escaped block decodes to the exact configured value.'
        );
        $this->assertSame(
            CanonicalJson::stringify($document),
            CanonicalJson::stringify($decoded),
            'The block carries the complete canonical document.'
        );
        $this->assertSame($html, $emitter->render('article-studio', 'article-studio-config', $document), 'Deterministic.');
        $this->assertSame(
            $match[1] ?? null,
            $emitter->document('article-studio', $document),
            'document() yields the same escaped JSON for a host-rendered element.'
        );
    }

    public function testAStandaloneDeploymentNeedsOnlyItsReleaseBinding(): void
    {
        $emitter = self::emitter();
        $document = self::fixture('studio-deployment.standalone.example.json');
        $document->mount = '#scratch';
        $document->release = $emitter->releaseBinding();
        $html = $emitter->render('scratch', 'scratch-config', $document);
        $this->assertStringContains('"kind":"studio-deployment"', $html, 'The standalone document is emitted.');
        $this->assertStringContains(
            '"version":"' . StudioContractResources::releaseRecord()->release() . '"',
            $html,
            'The binding carries the pinned release version.'
        );

        $document->launch = self::hosted()->launch;
        $refusal = $this->assertThrows(
            static fn () => $emitter->render('scratch', 'scratch-config', $document),
            DeploymentException::class,
            'A standalone document may not carry hosted members.'
        );
        $this->assertTrue($refusal instanceof DeploymentException, 'Typed refusal.');
        $this->assertSame('schema-invalid', $refusal->rejection(), 'The pinned schema refuses hosted members.');
    }

    public function testEveryDeploymentRefusalIsTypedAndFailsClosed(): void
    {
        $emitter = self::emitter();
        $cases = [
            'identifier' => [
                static function (\stdClass $document): array {
                    return ['1bad', 'config', $document];
                },
            ],
            'identifier-shared' => [
                static function (\stdClass $document): array {
                    return ['same', 'same', $document];
                },
            ],
            'kind' => [
                static function (\stdClass $document): array {
                    $document->kind = 'studio-config';

                    return ['article-studio', 'article-studio-config', $document];
                },
            ],
            'release-mismatch' => [
                static function (\stdClass $document): array {
                    $document->release->version = '0.1.0-beta.2';

                    return ['article-studio', 'article-studio-config', $document];
                },
            ],
            'schema-invalid' => [
                static function (\stdClass $document): array {
                    $document->unknownMember = true;

                    return ['article-studio', 'article-studio-config', $document];
                },
            ],
            'mount-mismatch' => [
                static function (\stdClass $document): array {
                    return ['another-target', 'article-studio-config', $document];
                },
            ],
            'resource-context' => [
                static function (\stdClass $document): array {
                    $document->session->resourceContext->key = 'contexts/another';

                    return ['article-studio', 'article-studio-config', $document];
                },
            ],
            'protocol' => [
                static function (\stdClass $document): array {
                    $document->session->hostCapabilities->protocolVersions = ['0.1.0-draft.1'];

                    return ['article-studio', 'article-studio-config', $document];
                },
            ],
            'routing-mismatch' => [
                static function (\stdClass $document): array {
                    $document->transport->routing->endpoints->{'authoring/list-types'} = '/api/studio/authoring/list-types';

                    return ['article-studio', 'article-studio-config', $document];
                },
            ],
            'oversized' => [
                static function (\stdClass $document): array {
                    $document->session->actor->displayName = str_repeat('x', 200);
                    $document->instanceId = str_repeat('y', 100);
                    $extensions = new \stdClass();
                    $extensions->{'org.example/padding'} = str_repeat('z', StudioDeploymentEmitter::MAXIMUM_BYTES);
                    $document->session->extensions = $extensions;

                    return ['article-studio', 'article-studio-config', $document];
                },
            ],
        ];
        foreach ($cases as $expected => [$mutate]) {
            [$mount, $configuration, $document] = $mutate(self::hosted());
            $refusal = $this->assertThrows(
                static fn () => $emitter->render($mount, $configuration, $document),
                DeploymentException::class,
                $expected . ' must be refused before any bytes are emitted.'
            );
            $this->assertTrue($refusal instanceof DeploymentException, 'Typed refusal for ' . $expected . '.');
            $this->assertSame(
                explode('-shared', $expected)[0],
                $refusal->rejection(),
                'The refusal for ' . $expected . ' carries its stable code.'
            );
        }
    }

    private static function emitter(): StudioDeploymentEmitter
    {
        return new StudioDeploymentEmitter(
            StudioDocumentSchemaRegistry::fromVendoredCorpus(),
            StudioContractResources::releaseRecord(),
        );
    }

    private static function hosted(): \stdClass
    {
        $document = self::fixture('studio-deployment.hosted.example.json');
        $document->mount = '#article-studio';
        $document->release = self::emitter()->releaseBinding();

        return $document;
    }

    private static function fixture(string $name): \stdClass
    {
        $decoded = CanonicalJson::decode(StudioContractResources::testkitBytes('fixtures/' . $name));
        if (!$decoded instanceof \stdClass) {
            throw new \RuntimeException('The deployment fixture is not an object.');
        }

        return $decoded;
    }
}
