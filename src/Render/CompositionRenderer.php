<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

use Kumwe\Producer\Css\BaseStylesheet;
use Kumwe\Producer\Css\ScopedStylesheet;

/**
 * The composition renderer of the semantic web profile.
 *
 * Walks a Blueprint's roots depth-first, wraps every node in its scoped,
 * attributed block element, resolves the node's renderer through the
 * registry, and assembles the page's HTML, its static CSS, and the requested
 * enhancements — a faithful PHP port of renderer-web's renderer.ts.
 * Rendering assembles bounded, escaped markup from data; it never evaluates
 * stored markup, styles, or scripts, and it never emits a script or style
 * element. Deterministic: identical input renders identical bytes.
 *
 * @since   0.1.0
 */
final class CompositionRenderer
{
    /**
     * The block type to renderer mapping consulted for every node.
     *
     * @since   0.1.0
     */
    private readonly BlockRendererRegistry $registry;

    /**
     * @param   ?BlockRendererRegistry  $registry  The renderer mapping to
     *     consult; null means the complete core catalog.
     * @since   0.1.0
     */
    public function __construct(?BlockRendererRegistry $registry = null)
    {
        $this->registry = $registry ?? BlockRendererRegistry::withCoreCatalog();
    }

    /**
     * Render a list of Blueprint root nodes into the page's HTML, its
     * static CSS (the base stylesheet followed by every non-empty per-node
     * scoped sheet, in encounter order, joined by newlines), and the
     * enhancement requests in document order.
     *
     * @param   list<\stdClass>  $roots    Decoded Blueprint nodes.
     * @param   ?RenderContext   $context  Host rendering authority; null
     *     means no binding resolver, no media resolver, no blob authority,
     *     and no scoped styles — every dependent block falls back closed.
     * @return  RenderResult  The immutable rendering outcome.
     * @throws  RenderException  When a node is not a decoded object or
     *     carries a node id outside the schema-valid identifier grammar.
     * @since   0.1.0
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
     * Render a decoded Blueprint document (an object carrying `roots`),
     * with the same guarantees as {@see self::render()}.
     *
     * @param   \stdClass       $document  The decoded Blueprint document.
     * @param   ?RenderContext  $context   Host rendering authority; null
     *     fails closed exactly as in {@see self::render()}.
     * @return  RenderResult  The immutable rendering outcome.
     * @throws  RenderException  When the document carries no roots list, or
     *     rendering the roots refuses as in {@see self::render()}.
     * @since   0.1.0
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
     * Render a node list into concatenated block markup, accumulating CSS
     * and enhancements on the shared state.
     *
     * @internal engine plumbing for {@see RenderState}
     *
     * @param   list<\stdClass>  $nodes  Decoded Blueprint nodes.
     * @param   RenderState      $state  The per-render accumulation.
     * @return  string  The nodes' escaped markup, in document order.
     * @throws  RenderException  When a node is not a decoded object or its
     *     id fails {@see self::scopeFor()}.
     * @since   0.1.0
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

    /**
     * Render one node: compile any host scoped-style intent registered for
     * its id, then wrap the type-specific content in the scoped wrapper
     * element carrying the block, node, scope, and presentation data
     * attributes — every attribute value escaped. Description items and
     * navigation items get their reference wrapper shapes; every other
     * type wraps in a div.
     *
     * @param   \stdClass    $node   The decoded Blueprint node.
     * @param   RenderState  $state  The per-render accumulation.
     * @return  string  The node's complete escaped block markup.
     * @throws  RenderException  When the node id fails
     *     {@see self::scopeFor()} or its scoped-style intent is refused.
     * @since   0.1.0
     */
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

    /**
     * The node's inner markup from its registered renderer, or — for a
     * type without one — the reference's bounded unknown-type fallback: a
     * labeled status paragraph naming the escaped type. An unknown type is
     * never an error; the page still renders.
     *
     * @param   \stdClass    $node   The decoded Blueprint node.
     * @param   string       $type   The node's block type identifier.
     * @param   string       $scope  The node's CSS-safe scope token.
     * @param   RenderState  $state  The per-render accumulation.
     * @return  string  Escaped inner markup for the node.
     * @since   0.1.0
     */
    private function renderType(\stdClass $node, string $type, string $scope, RenderState $state): string
    {
        $renderer = $this->registry->rendererFor($type);
        if ($renderer === null) {
            return '<p role="status">Unsupported Studio block ' . SafeMarkup::escapeHtml($type) . '</p>';
        }

        return $renderer->render($node, $scope, $state);
    }

    /**
     * The node's `data-studio-*` presentation attributes from its stored
     * design intent. Only the contract's closed, enumerated design choices
     * can appear; intent that fails the closed grammar yields no attributes
     * at all rather than an error. An animation other than `none` also
     * records a motion enhancement request.
     *
     * @param   \stdClass    $node   The decoded Blueprint node.
     * @param   string       $scope  The node's CSS-safe scope token.
     * @param   RenderState  $state  The per-render accumulation.
     * @return  string  The escaped attribute fragment; empty for no intent.
     * @since   0.1.0
     */
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
     * The deterministic CSS-safe scope token for a schema-valid node id:
     * `s` followed by the id's lowercase hex bytes, so distinct ids can
     * never collide and the token needs no CSS escaping anywhere.
     *
     * @param   string  $nodeId  The node id; must match the schema's stable
     *     identifier grammar (an alphanumeric first character, then at most
     *     239 further characters of `[A-Za-z0-9._:/-]`).
     * @return  string  The scope token, identical for identical ids.
     * @throws  RenderException  When the id fails the identifier grammar.
     * @since   0.1.0
     */
    public static function scopeFor(string $nodeId): string
    {
        if (preg_match('%^[A-Za-z0-9][A-Za-z0-9._:/-]{0,239}$%u', $nodeId) !== 1) {
            throw new RenderException('Studio node id must be a schema-valid stable identifier.');
        }

        return 's' . bin2hex($nodeId);
    }

    /**
     * The short block name emitted in `data-studio-block`: the type with
     * its namespace prefix removed up to the first slash, or the whole
     * type when it carries no slash.
     *
     * @param   string  $type  The block type identifier.
     * @return  string  The type's short name.
     * @since   0.1.0
     */
    private static function blockName(string $type): string
    {
        $position = strpos($type, '/');

        return $position === false ? $type : substr($type, $position + 1);
    }
}
