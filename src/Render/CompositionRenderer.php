<?php

/**
 * The composition renderer of the semantic web profile.
 *
 * Walks a Blueprint's roots depth-first, wraps every node in its scoped,
 * attributed block element, resolves the node's renderer through the
 * registry, and assembles the page's HTML, its static CSS, and the requested
 * enhancements — a faithful PHP port of renderer-web's renderer.ts.
 * Rendering assembles bounded, escaped markup from data; it never evaluates
 * stored markup, styles, or scripts, and it never emits a script or style
 * element.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render;

use Kumwe\Producer\Css\BaseStylesheet;
use Kumwe\Producer\Css\ScopedStylesheet;

final class CompositionRenderer
{
    private readonly BlockRendererRegistry $registry;

    public function __construct(?BlockRendererRegistry $registry = null)
    {
        $this->registry = $registry ?? BlockRendererRegistry::withCoreCatalog();
    }

    /**
     * Render a list of Blueprint root nodes.
     *
     * @param list<\stdClass> $roots decoded Blueprint nodes
     */
    public function render(array $roots, ?RenderContext $context = null): RenderResult
    {
        $state = new RenderState($context ?? new RenderContext(), $this);
        $html = $this->renderNodes($roots, $state);
        $parts = [BaseStylesheet::css(), ...$state->css];
        $css = implode("\n", array_values(array_filter($parts, static fn (string $part): bool => $part !== '')));

        return new RenderResult($html, $css, $state->enhancements);
    }

    /**
     * Render a decoded Blueprint document (an object carrying `roots`).
     */
    public function renderDocument(\stdClass $document, ?RenderContext $context = null): RenderResult
    {
        $roots = $document->roots ?? null;
        if (!is_array($roots)) {
            throw new RenderException('A Blueprint document must carry a roots list.');
        }

        return $this->render($roots, $context);
    }

    /**
     * @internal engine plumbing for {@see RenderState}
     *
     * @param list<\stdClass> $nodes
     */
    public function renderNodes(array $nodes, RenderState $state): string
    {
        $html = '';
        foreach ($nodes as $node) {
            if (!$node instanceof \stdClass) {
                throw new RenderException('Blueprint nodes must be decoded objects.');
            }
            $html .= $this->renderNode($node, $state);
        }

        return $html;
    }

    private function renderNode(\stdClass $node, RenderState $state): string
    {
        $id = Properties::stringValue($node->id ?? null);
        $scope = self::scopeFor($id);
        if (array_key_exists($id, $state->context->scopedStyles)) {
            $sheet = $state->context->scopedStyles[$id];
            if (is_object($sheet) || is_array($sheet)) {
                $state->css[] = ScopedStylesheet::compile($scope, $sheet);
            }
        }
        $presentation = $this->presentationAttributes($node, $scope, $state);
        $type = Properties::stringValue($node->type ?? null);
        $content = $this->renderType($node, $type, $scope, $state);
        $attributes = 'data-studio-block="' . SafeMarkup::escapeAttribute(self::blockName($type)) . '"'
            . ' data-studio-node="' . SafeMarkup::escapeAttribute($id) . '"'
            . ' data-studio-scope="' . $scope . '"' . $presentation;
        if ($type === BlockTypes::DESCRIPTION_ITEM) {
            return '<div data-studio-description-item ' . $attributes . '>' . $content . '</div>';
        }
        if ($type === BlockTypes::NAVIGATION_ITEM) {
            return '<li data-studio-navigation-item ' . $attributes . '>' . $content . '</li>';
        }

        return '<div ' . $attributes . '>' . $content . '</div>';
    }

    private function renderType(\stdClass $node, string $type, string $scope, RenderState $state): string
    {
        $renderer = $this->registry->rendererFor($type);
        if ($renderer === null) {
            return '<p role="status">Unsupported Studio block ' . SafeMarkup::escapeHtml($type) . '</p>';
        }

        return $renderer->render($node, $scope, $state);
    }

    private function presentationAttributes(\stdClass $node, string $scope, RenderState $state): string
    {
        if (!isset($node->properties->design)) {
            return '';
        }
        try {
            $intent = ProductionValues::parsePresentationIntent($node->properties->design);
        } catch (\Throwable) {
            return '';
        }
        $animation = $intent->animation ?? null;
        if ($animation !== null && $animation !== 'none') {
            $state->enhance('motion', $node, $scope, ['animation' => $animation]);
        }
        $attributes = [
            ['align', $intent->align ?? null],
            ['animation', $animation],
            ['height', $intent->height ?? null],
            ['inverse', $intent->inverse ?? null],
            ['margin', $intent->margin ?? null],
            ['marker', $intent->marker ?? null],
            ['padding', $intent->padding ?? null],
            ['position', $intent->position ?? null],
            ['print', $intent->print ?? null],
            ['scroll', $intent->scrolling ?? null],
            ['visible-compact', $intent->visibility->compact ?? null],
            ['visible-medium', $intent->visibility->medium ?? null],
            ['visible-expanded', $intent->visibility->expanded ?? null],
            ['width', $intent->width ?? null],
        ];
        $out = '';
        foreach ($attributes as [$name, $value]) {
            if ($value === null) {
                continue;
            }
            $text = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            $out .= ' data-studio-' . $name . '="' . SafeMarkup::escapeAttribute($text) . '"';
        }

        return $out;
    }

    /**
     * The deterministic CSS-safe scope token for a schema-valid node id.
     */
    public static function scopeFor(string $nodeId): string
    {
        if (preg_match('%^[A-Za-z0-9][A-Za-z0-9._:/-]{0,239}$%u', $nodeId) !== 1) {
            throw new RenderException('Studio node id must be a schema-valid stable identifier.');
        }

        return 's' . bin2hex($nodeId);
    }

    private static function blockName(string $type): string
    {
        $position = strpos($type, '/');

        return $position === false ? $type : substr($type, $position + 1);
    }
}
