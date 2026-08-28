<?php

/**
 * Canonical rich text: replay the projection conformance corpus and prove
 * the HTML rendering and the fail-closed grammar.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Render\CompositionRenderer;
use Kumwe\Producer\Render\RenderException;
use Kumwe\Producer\Render\RichText;
use Kumwe\Producer\Tests\TestCase;

final class RenderRichTextTest extends TestCase
{
    public function testReplaysTheRichTextProjectionCorpus(): void
    {
        $directory = dirname(__DIR__, 2) . '/resources/studio-contract/conformance/rich-text';
        $files = glob($directory . '/*.json') ?: [];
        sort($files);
        $this->assertSame(8, count($files), 'The rich-text corpus must be vendored completely.');

        foreach ($files as $file) {
            $label = basename($file, '.json');
            $vector = CanonicalJson::decode((string) file_get_contents($file));
            $document = RichText::parse($vector->document);
            $this->assertSame(
                CanonicalJson::stringify($vector->projection),
                CanonicalJson::stringify(RichText::project($document)),
                "{$label} must project to the canonical block projection."
            );
            $html = RichText::render($document);
            $this->assertStringExcludes('<script', $html, "{$label} rendering must stay inert.");
        }
    }

    public function testRendersTheSemanticHtmlProjection(): void
    {
        $document = RichText::parse(CanonicalJson::decode(
            '{"type":"doc","content":['
            . '{"type":"heading","attrs":{"level":3},"content":[{"type":"text","text":"Care"}]},'
            . '{"type":"paragraph","content":['
            . '{"type":"text","text":"cold","marks":[{"type":"italic"},{"type":"bold"}]},'
            . '{"type":"hardBreak"},'
            . '{"type":"text","text":"x < y","marks":[{"type":"code"}]}]},'
            . '{"type":"orderedList","attrs":{"start":3},"content":[{"type":"listItem","content":['
            . '{"type":"paragraph","content":[{"type":"text","text":"Step"}]}]}]},'
            . '{"type":"blockquote","content":[{"type":"paragraph","content":['
            . '{"type":"text","text":"mark","marks":[{"type":"highlight","attrs":{"tone":"warning"}}]}]}]},'
            . '{"type":"horizontalRule"}]}'
        ));
        $html = RichText::render($document);
        $this->assertStringContains('<h3>Care</h3>', $html, 'Headings keep their configured level.');
        $this->assertStringContains('<em><strong>cold</strong></em>', $html, 'Marks nest in the reference order.');
        $this->assertStringContains('<br>', $html, 'Hard breaks render as br.');
        $this->assertStringContains('<code>x &lt; y</code>', $html, 'Code marks escape their text.');
        $this->assertStringContains('<ol start="3"><li><p>Step</p></li></ol>', $html, 'Ordered lists keep their start.');
        $this->assertStringContains(
            '<blockquote><p><mark data-studio-tone="warning">mark</mark></p></blockquote>',
            $html,
            'Highlight marks carry their tone.'
        );
        $this->assertStringContains('<hr>', $html, 'Horizontal rules render.');
    }

    public function testChecklistBridgesSkippedLevels(): void
    {
        $document = RichText::parse(CanonicalJson::decode(
            '{"type":"doc","content":[{"type":"checklist","content":['
            . '{"type":"checklistItem","attrs":{"checked":false,"level":2},"content":[{"type":"text","text":"Deep"}]}]}]}'
        ));
        $html = RichText::render($document);
        $this->assertStringContains('data-studio-rich-text-checklist-bridge', $html, 'A skipped level renders a bridge item.');
        $this->assertStringContains('data-studio-level="2" aria-level="3"', $html, 'The item keeps its declared level.');
        $this->assertStringContains('<input type="checkbox" disabled', $html, 'The checkbox stays inert.');
    }

    public function testRefusesDocumentsOutsideThePortableGrammar(): void
    {
        $hostile = [
            'a stored element node' => '{"type":"doc","content":[{"type":"script","text":"alert(1)"}]}',
            'a disallowed mark' => '{"type":"doc","content":[{"type":"paragraph","content":['
                . '{"type":"text","text":"x","marks":[{"type":"link"}]}]}]}',
            'an unknown attribute' => '{"type":"doc","content":[{"type":"paragraph","attrs":{"onclick":"x"},'
                . '"content":[{"type":"text","text":"x"}]}]}',
            'a non-doc root' => '{"type":"paragraph","content":[{"type":"text","text":"x"}]}',
            'an empty document' => '{"type":"doc","content":[]}',
            'an invalid callout tone' => '{"type":"doc","content":[{"type":"callout","attrs":{"tone":"evil"},'
                . '"content":[{"type":"paragraph","content":[{"type":"text","text":"x"}]}]}]}',
        ];
        foreach ($hostile as $label => $json) {
            $value = CanonicalJson::decode($json);
            $this->assertThrows(
                static fn (): \stdClass => RichText::parse($value),
                RenderException::class,
                "The grammar must refuse {$label}."
            );
        }
    }

    public function testRefusedDocumentFallsBackToEscapedText(): void
    {
        $node = (object) [
            'bindings' => (object) [
                'content' => (object) [
                    'source' => (object) ['kind' => 'static-value', 'value' => '<b>raw html string</b>'],
                ],
            ],
            'id' => 'fallback-rich-text',
            'properties' => new \stdClass(),
            'slots' => new \stdClass(),
            'type' => 'studio.core/rich-text',
            'version' => '1.0.0',
        ];
        $result = (new CompositionRenderer())->render([$node]);
        $this->assertStringContains('<div data-studio-part="content"></div>', $result->html, 'A refused document renders empty content.');
        $this->assertStringExcludes('<b>raw html string</b>', $result->html, 'Stored markup strings are never evaluated.');
    }
}
