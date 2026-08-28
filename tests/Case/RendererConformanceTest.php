<?php

/**
 * Replay Studio's renderer-web conformance corpus.
 *
 * Every vector builds a composition from its roots, bindings, and media,
 * renders it through the composition renderer, and fixes required and
 * forbidden HTML substrings, required CSS substrings, and the exact
 * requested-enhancement list. Passing all vectors is what makes the PHP
 * renderer's semantics the reference renderer's semantics.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Render\CompositionRenderer;
use Kumwe\Producer\Render\RenderContext;
use Kumwe\Producer\Render\ResolvedMedia;
use Kumwe\Producer\Tests\TestCase;

final class RendererConformanceTest extends TestCase
{
    public function testReplaysTheCompleteRendererCorpus(): void
    {
        $renderer = new CompositionRenderer();
        foreach ($this->vectors() as $vector) {
            $label = $vector->id;
            $result = $renderer->render($vector->roots, self::contextFor($vector));

            foreach ($vector->expect->htmlContains as $needle) {
                $this->assertStringContains($needle, $result->html, "{$label} HTML must contain the expected markup.");
            }
            foreach ($vector->expect->htmlExcludes as $needle) {
                $this->assertStringExcludes($needle, $result->html, "{$label} HTML must not contain forbidden markup.");
            }
            foreach ($vector->expect->cssContains as $needle) {
                $this->assertStringContains($needle, $result->css, "{$label} CSS must contain the expected rules.");
            }
            $this->assertSame(
                $vector->expect->enhancements,
                $result->enhancementNames(),
                "{$label} must request exactly the expected enhancements."
            );
        }
    }

    public function testRendersByteIdenticallyAcrossRuns(): void
    {
        foreach ($this->vectors() as $vector) {
            $first = (new CompositionRenderer())->render($vector->roots, self::contextFor($vector));
            $second = (new CompositionRenderer())->render($vector->roots, self::contextFor($vector));
            $this->assertSame($first->html, $second->html, "{$vector->id} HTML must be deterministic.");
            $this->assertSame($first->css, $second->css, "{$vector->id} CSS must be deterministic.");
            $this->assertSame(
                $first->enhancementNames(),
                $second->enhancementNames(),
                "{$vector->id} enhancements must be deterministic."
            );
        }
    }

    /**
     * @return list<\stdClass>
     */
    private function vectors(): array
    {
        $directory = dirname(__DIR__, 2) . '/resources/studio-contract/conformance/renderer-web';
        $files = glob($directory . '/*.json') ?: [];
        sort($files);
        $this->assertSame(8, count($files), 'The renderer-web corpus must be vendored completely.');
        $vectors = [];
        foreach ($files as $file) {
            $vectors[] = CanonicalJson::decode((string) file_get_contents($file));
        }

        return $vectors;
    }

    private static function contextFor(\stdClass $vector): RenderContext
    {
        $bindings = [];
        foreach ($vector->bindings as $binding) {
            $bindings[$binding->nodeId . "\u{0}" . $binding->port] = $binding->value;
        }
        $media = [];
        foreach ($vector->media as $descriptor) {
            $media[$descriptor->assetId] = $descriptor;
        }

        return new RenderContext(
            allowBlobMedia: ($vector->context->allowBlobMedia ?? false) === true,
            resolveBinding: static function (\stdClass $node, string $port) use ($bindings): mixed {
                $key = ($node->id ?? '') . "\u{0}" . $port;

                return array_key_exists($key, $bindings) ? $bindings[$key] : null;
            },
            resolveMedia: static function (\stdClass $reference) use ($media): ?ResolvedMedia {
                $descriptor = $media[$reference->assetId] ?? null;

                return $descriptor === null ? null : ResolvedMedia::fromDescriptor($descriptor);
            },
        );
    }
}
