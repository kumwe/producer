<?php

/**
 * Prove the minimal-host example is truthful: the wire path it documents
 * answers the demo operation with the canonical schema-shaped result and
 * refuses an unauthorized actor with the canonical error, and the render
 * path produces the page its README describes — the demo content, the
 * unknown-type fallback, the reduced-motion base, and deterministic bytes.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Tests\TestCase;
use Kumwe\Producer\Wire\Dispatcher;
use Kumwe\Producer\Wire\RequestEnvelope;
use Kumwe\ProducerExamples\DemoPage;
use Kumwe\ProducerExamples\MinimalHost;

require_once dirname(__DIR__, 2) . '/examples/minimal-host/MinimalHost.php';

final class ExampleHostTest extends TestCase
{
    private static function loadEnvelope(): string
    {
        return json_encode([
            'arguments' => ['id' => 'examples/welcome'],
            'context' => [
                'operationId' => 'studio.operation/artifact.load',
                'protocolVersion' => RequestEnvelope::WIRE_PROTOCOL_VERSION,
                'requestId' => 'requests/example-1',
                'resourceContextKey' => 'contexts/example',
                'sessionGeneration' => 'session-1',
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function testTheDemoWireOperationAnswersWithTheSchemaShapedResult(): void
    {
        $dispatcher = new Dispatcher(new MinimalHost(MinimalHost::DEMO_ACTOR));
        $response = $dispatcher->dispatch('artifact/load', self::loadEnvelope());

        $this->assertSame(false, $response->refusal, 'The demo actor loads the demo artifact.');
        $this->assertSame('application/json', $response->headers['content-type'], 'Wire content-type discipline holds.');
        $this->assertSame(
            CanonicalJson::stringify(CanonicalJson::decode($response->body)),
            $response->body,
            'The response body is canonical bytes.'
        );

        $document = CanonicalJson::decode($response->body);
        $this->assertSame('blueprint', $document->value->kind, 'The result carries the stored composition.');
        $this->assertSame('examples/welcome', $document->value->id, 'The result names the demo artifact.');
        $this->assertSame('examples/welcome-r1', $document->value->revision, 'The result carries the current revision.');
        $this->assertTrue(
            is_array($document->value->roots) && $document->value->roots !== [],
            'The composition carries its Blueprint roots.'
        );
    }

    public function testAnUnauthorizedActorReceivesTheCanonicalRefusal(): void
    {
        $forbidden = (new Dispatcher(new MinimalHost('intruder')))
            ->dispatch('artifact/load', self::loadEnvelope());
        $this->assertSame(true, $forbidden->refusal, 'A foreign actor is refused.');
        $document = CanonicalJson::decode($forbidden->body);
        $this->assertSame('host-error', $document->kind, 'The refusal is the canonical error document.');
        $this->assertSame('forbidden', $document->category, 'The host authorization answer is emitted verbatim.');
        $this->assertStringContains(
            'examples.minimal-host/actor-forbidden',
            $forbidden->body,
            'The refusal carries the host stable message key.'
        );

        $unauthenticated = (new Dispatcher(new MinimalHost(null)))
            ->dispatch('artifact/load', self::loadEnvelope());
        $this->assertSame(true, $unauthenticated->refusal, 'A missing actor is refused.');
        $this->assertSame(
            'unauthenticated',
            CanonicalJson::decode($unauthenticated->body)->category,
            'No transport identity means unauthenticated.'
        );
    }

    public function testTheDemoPageRendersTheCompositionFaithfully(): void
    {
        $result = DemoPage::render();

        $this->assertStringContains(
            'Studio designs it. Producer makes it real.',
            $result->html,
            'The heading content reaches the page.'
        );
        $this->assertStringContains(
            'rendered to semantic HTML by Kumwe Producer',
            $result->html,
            'The rich-text paragraph reaches the page.'
        );
        $this->assertStringContains('The wire', $result->html, 'The card content reaches the page.');
        $this->assertStringContains(
            'Unsupported Studio block example.blocks/guestbook',
            $result->html,
            'The unknown block type renders the bounded semantic fallback.'
        );
        $this->assertStringExcludes('<script', $result->html, 'The rendered fragment never carries a script.');
        $this->assertSame(
            ['notice'],
            $result->enhancementNames(),
            'The dismissible notice is the page runtime need signal.'
        );

        $stylesheet = DemoPage::stylesheet($result);
        $this->assertStringContains(
            'prefers-reduced-motion',
            $stylesheet,
            'The reduced-motion base is part of the page stylesheet.'
        );
        $this->assertStringContains(
            '--studio-space:1.25rem',
            $stylesheet,
            'The theme tokens compile into the page stylesheet.'
        );
        $this->assertStringContains(
            '--studio-columns-medium:2',
            $stylesheet,
            'The responsive grid contributes its scoped column rules.'
        );
    }

    public function testTheDemoRenderIsDeterministicAcrossRuns(): void
    {
        $first = DemoPage::render();
        $second = DemoPage::render();
        $this->assertSame($first->html, $second->html, 'The demo page HTML is identical bytes across runs.');
        $this->assertSame(
            DemoPage::stylesheet($first),
            DemoPage::stylesheet($second),
            'The demo page stylesheet is identical bytes across runs.'
        );
    }
}
