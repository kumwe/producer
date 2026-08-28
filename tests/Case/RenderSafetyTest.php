<?php

/**
 * The renderer's security discipline under hostile input.
 *
 * Escaping of stored text and attributes, the closed URL allowlist, blob
 * media policy, the unknown-block fallback, safe-markup fail-closed
 * behavior, node id validation, and deterministic output.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Render\CompositionRenderer;
use Kumwe\Producer\Render\RenderContext;
use Kumwe\Producer\Render\RenderException;
use Kumwe\Producer\Render\ResolvedMedia;
use Kumwe\Producer\Render\SafeMarkup;
use Kumwe\Producer\Tests\TestCase;

final class RenderSafetyTest extends TestCase
{
    public function testEscapesHostileTextEverywhere(): void
    {
        $hostile = '<img src=x onerror=alert(1)>"&\'';
        $result = (new CompositionRenderer())->render(
            [self::node('hostile-heading', 'studio.core/heading', ['level' => 2], [], ['text' => $hostile])]
        );
        $this->assertStringContains('&lt;img src=x onerror=alert(1)&gt;"&amp;\'', $result->html, 'Text content must be escaped.');
        $this->assertStringExcludes('<img', $result->html, 'Hostile markup must never survive as markup.');
    }

    public function testEscapesHostileAttributeValues(): void
    {
        $context = new RenderContext(
            resolveMedia: static fn (\stdClass $reference): ResolvedMedia => new ResolvedMedia(
                src: 'https://cdn.example.test/a.png',
                altText: '"><script>alert(1)</script>',
            ),
        );
        $result = (new CompositionRenderer())->render(
            [self::node('hostile-image', 'studio.core/image', [], [], [
                'asset' => (object) ['kind' => 'media-reference', 'assetId' => 'a'],
            ])],
            $context
        );
        $this->assertStringContains('alt="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $result->html, 'Attribute values must be escaped.');
        $this->assertStringExcludes('<script', $result->html, 'Attribute injection must be impossible.');
    }

    public function testVetsUrlsThroughTheClosedAllowlist(): void
    {
        $this->assertSame('/path?a=1', SafeMarkup::safeUrl('/path?a=1'), 'Site-relative URLs pass.');
        $this->assertSame('#anchor', SafeMarkup::safeUrl('#anchor'), 'Fragment URLs pass.');
        $this->assertSame('https://example.test/x', SafeMarkup::safeUrl('https://example.test/x'), 'https URLs pass.');
        $this->assertSame('https://example.test:8443/x', SafeMarkup::safeUrl('https://example.test:8443/x'), 'https URLs with ports pass.');
        foreach (
            [
                'javascript:alert(1)',
                'data:text/html,<script>alert(1)</script>',
                'http://example.test/x',
                'vbscript:x',
                'https:example',
                'blob:https://example.test/x',
                ' https://example.test/x',
            ] as $unsafe
        ) {
            $this->assertSame(null, SafeMarkup::safeUrl($unsafe), "URL must be refused: {$unsafe}");
        }
    }

    public function testRefusedActionUrlRendersInertText(): void
    {
        $result = (new CompositionRenderer())->render(
            [self::node('unsafe-action', 'studio.core/call-to-action', ['href' => 'javascript:alert(1)'], [], ['label' => 'Go'])]
        );
        $this->assertStringContains('<span data-studio-part="action">Go</span>', $result->html, 'A refused URL must render as inert text.');
        $this->assertStringExcludes('javascript:', $result->html, 'The refused URL must never be emitted.');
        $this->assertStringExcludes('href=', $result->html, 'No href may be emitted for a refused URL.');
    }

    public function testBlobMediaIsDeniedByDefaultAndActiveTypesAlways(): void
    {
        $blob = new ResolvedMedia(src: 'blob:https://example.test/asset-1', mediaType: 'image/png');
        $this->assertSame(null, SafeMarkup::safeMediaUrl($blob, false), 'Blob URLs are denied without host authority.');
        $this->assertSame($blob->src, SafeMarkup::safeMediaUrl($blob, true), 'Blob URLs pass under explicit authority.');
        $svg = new ResolvedMedia(src: 'blob:https://example.test/asset-2', mediaType: 'image/svg+xml');
        $this->assertSame(null, SafeMarkup::safeMediaUrl($svg, true), 'Active SVG blobs stay denied.');
        $html = new ResolvedMedia(src: 'blob:https://example.test/asset-3', mediaType: 'TEXT/HTML');
        $this->assertSame(null, SafeMarkup::safeMediaUrl($html, true), 'HTML blobs stay denied case-insensitively.');
        $data = new ResolvedMedia(src: 'data:image/png;base64,AAAA', mediaType: 'image/png');
        $this->assertSame(null, SafeMarkup::safeMediaUrl($data, true), 'data: URLs are always denied.');
    }

    public function testUnknownBlockRendersBoundedLabeledFallback(): void
    {
        $result = (new CompositionRenderer())->render([
            self::node('known', 'studio.core/heading', ['level' => 2], [], ['text' => 'Fine']),
            self::node('unknown', 'acme.custom/widget<script>', ['secret' => 'do-not-leak'], [], ['payload' => 'secret-value']),
        ]);
        $this->assertStringContains('Unsupported Studio block acme.custom/widget&lt;script&gt;', $result->html, 'The fallback must label the escaped type.');
        $this->assertStringContains('<h2 data-studio-part="heading">Fine</h2>', $result->html, 'The page render must still succeed.');
        $this->assertStringExcludes('do-not-leak', $result->html, 'Unknown block properties must not leak.');
        $this->assertStringExcludes('secret-value', $result->html, 'Unknown block bindings must not leak.');
        $this->assertStringExcludes('<script', $result->html, 'The fallback must escape the type id.');
    }

    public function testSafeMarkupFragmentFailsClosed(): void
    {
        $fragment = CanonicalJson::decode(
            '{"kind":"safe-markup-fragment","policy":"studio.rich-text/marketing","nodes":['
            . '{"kind":"element","tag":"p","children":[{"kind":"text","value":"a<b"}]},'
            . '{"kind":"element","tag":"a","attributes":{"href":"/doc"},"children":[{"kind":"text","value":"link"}]}]}'
        );
        $this->assertSame(
            '<p>a&lt;b</p><a href="/doc">link</a>',
            SafeMarkup::renderFragment($fragment),
            'Allowed structural markup must render escaped.'
        );

        $script = CanonicalJson::decode(
            '{"kind":"safe-markup-fragment","policy":"studio.rich-text/marketing","nodes":['
            . '{"kind":"element","tag":"script","children":[]}]}'
        );
        $this->assertThrows(
            static fn (): string => SafeMarkup::renderFragment($script),
            RenderException::class,
            'A disallowed tag must be refused.'
        );

        $unsafeHref = CanonicalJson::decode(
            '{"kind":"safe-markup-fragment","policy":"studio.rich-text/marketing","nodes":['
            . '{"kind":"element","tag":"a","attributes":{"href":"javascript:alert(1)"},"children":[]}]}'
        );
        $this->assertThrows(
            static fn (): string => SafeMarkup::renderFragment($unsafeHref),
            RenderException::class,
            'A forbidden URL must be refused.'
        );

        $badAttribute = CanonicalJson::decode(
            '{"kind":"safe-markup-fragment","policy":"studio.rich-text/marketing","nodes":['
            . '{"kind":"element","tag":"p","attributes":{"onclick":"x"},"children":[]}]}'
        );
        $this->assertThrows(
            static fn (): string => SafeMarkup::renderFragment($badAttribute),
            RenderException::class,
            'An attribute outside the allowlist must be refused.'
        );
    }

    public function testRefusesInvalidNodeIdentifiers(): void
    {
        $this->assertThrows(
            static fn (): mixed => (new CompositionRenderer())->render(
                [self::node('bad id"', 'studio.core/heading', [], [], [])]
            ),
            RenderException::class,
            'A node id outside the schema grammar must be refused.'
        );
    }

    public function testRendersDeterministically(): void
    {
        $roots = [
            self::node('page', 'studio.core/section', [], [
                self::node('page-grid', 'studio.core/grid', ['columns' => 2], [
                    self::node('page-heading', 'studio.core/heading', ['level' => 2], [], ['text' => 'Title']),
                    self::node('page-action', 'studio.core/call-to-action', ['href' => 'https://example.test/'], [], ['label' => 'Go']),
                ]),
            ]),
        ];
        $first = (new CompositionRenderer())->render($roots);
        $second = (new CompositionRenderer())->render($roots);
        $this->assertSame($first->html, $second->html, 'Two renders must be byte-identical HTML.');
        $this->assertSame($first->css, $second->css, 'Two renders must be byte-identical CSS.');
    }

    /**
     * @param array<string, mixed> $properties
     * @param list<\stdClass> $children
     * @param array<string, mixed> $bindings static-value port bindings
     */
    private static function node(
        string $id,
        string $type,
        array $properties = [],
        array $children = [],
        array $bindings = []
    ): \stdClass {
        $slot = match ($type) {
            'studio.core/section', 'studio.core/accordion-item', 'studio.core/dialog',
            'studio.core/popover', 'studio.core/tab', 'studio.core/article',
            'studio.core/cover' => 'content',
            'studio.core/card' => 'actions',
            'studio.core/navigation-item' => 'children',
            default => 'items',
        };
        $boundPorts = new \stdClass();
        foreach ($bindings as $port => $value) {
            $boundPorts->{$port} = (object) [
                'source' => (object) ['kind' => 'static-value', 'value' => $value],
            ];
        }

        return (object) [
            'bindings' => $boundPorts,
            'id' => $id,
            'properties' => (object) $properties,
            'slots' => $children === [] ? new \stdClass() : (object) [$slot => $children],
            'type' => $type,
            'version' => '1.0.0',
        ];
    }
}
