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
     * @param   ?BlockRendererRegistry  $registry  The exact registrations
     *     and draft implementations to consult; null means the complete
     *     non-authoritative core draft catalog.
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
        return $this->renderWithCoordinates($roots, $context ?? new RenderContext(), []);
    }

    /**
     * Render roots against a preflighted dependency-lock index.
     *
     * Strict published policy first proves every lock has one trusted exact
     * registration. Draft policy may use the sole registered revision for
     * a type/version and retains renderer-web's bounded fallback.
     *
     * @param   list<\stdClass>                     $roots        Blueprint roots.
     * @param   RenderContext                       $context      Host authority.
     * @param   array<string, BlockCoordinate|null> $coordinates Exact locks;
     *                                                          null is ambiguous.
     *
     * @return  RenderResult  Immutable rendering outcome.
     *
     * @throws  RenderException  When strict lock proof or rendering fails.
     *
     * @since   0.2.0
     */
    private function renderWithCoordinates(
        array $roots,
        RenderContext $context,
        array $coordinates,
    ): RenderResult {
        if ($context->policy === RenderPolicy::RequireRegistered) {
            foreach ($coordinates as $coordinate) {
                if (!$coordinate instanceof BlockCoordinate || !$this->registry->supports($coordinate)) {
                    throw new RenderException('A published block dependency lock is ambiguous or unavailable.');
                }
            }
        }
        $state = new RenderState($context, $this, $coordinates);
        $html = $this->renderNodes($roots, $state);
        if (
            $state->context->previewMarkerMap !== null
            && count($state->previewMarkers) !== count($state->context->previewMarkerMap)
        ) {
            throw new RenderException('The preview marker inventory does not match the rendered tree.');
        }
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

        return $this->renderWithCoordinates(
            $roots,
            $context ?? new RenderContext(),
            self::blockCoordinates($document),
        );
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
        $previewMarker = '';
        if ($state->context->previewMarkerMap !== null) {
            $marker = $state->context->previewMarkerFor($id);
            if ($marker === null || isset($state->previewMarkers[$marker])) {
                throw new RenderException('The preview marker inventory does not identify each node exactly once.');
            }
            $state->previewMarkers[$marker] = true;
            $previewMarker = ' data-studio-preview-marker="' . SafeMarkup::escapeAttribute($marker) . '"';
        }
        $attributes = 'data-studio-block="' . SafeMarkup::escapeAttribute(self::blockName($type)) . '"'
            . ' data-studio-node="' . SafeMarkup::escapeAttribute($id) . '"'
            . ' data-studio-scope="' . $scope . '"' . $previewMarker . $presentation;
        $hidden = $state->isNodeHidden($id) ? ' hidden' : '';
        if ($type === BlockTypes::DESCRIPTION_ITEM) {
            return '<div data-studio-description-item ' . $attributes . $hidden . '>' . $content . '</div>';
        }
        if ($type === BlockTypes::NAVIGATION_ITEM) {
            return '<li data-studio-navigation-item ' . $attributes . $hidden . '>' . $content . '</li>';
        }

        return '<div ' . $attributes . $hidden . '>' . $content . '</div>';
    }

    /**
     * The node's inner markup from its coordinate-bound renderer. Draft
     * policy keeps the reference's bounded unknown-type fallback. Strict
     * published policy refuses a missing, ambiguous, or unregistered lock.
     *
     * @param   \stdClass    $node   The decoded Blueprint node.
     * @param   string       $type   The node's block type identifier.
     * @param   string       $scope  The node's CSS-safe scope token.
     * @param   RenderState  $state  The per-render accumulation.
     * @return  string  Escaped inner markup for the node.
     * @throws  RenderException  When strict policy cannot resolve exactly.
     *
     * @since   0.2.0
     */
    private function renderType(\stdClass $node, string $type, string $scope, RenderState $state): string
    {
        $version = Properties::stringValue($node->version ?? null);
        $coordinate = $state->blockCoordinates[$type . "\0" . $version] ?? null;
        if ($state->context->policy === RenderPolicy::RequireRegistered) {
            if (!$coordinate instanceof BlockCoordinate) {
                throw new RenderException('A published node has no unambiguous dependency lock.');
            }
            $renderer = $this->registry->rendererFor($coordinate);
            if ($renderer === null) {
                throw new RenderException('A published node renderer is unavailable at its exact coordinate.');
            }

            return $renderer->render($node, $scope, $state);
        }
        $renderer = $coordinate instanceof BlockCoordinate
            ? $this->registry->rendererFor($coordinate)
            : null;
        $renderer ??= $this->registry->draftRendererFor($type, $version);
        if ($renderer === null) {
            return '<p role="status">Unsupported Studio block ' . SafeMarkup::escapeHtml($type) . '</p>';
        }

        return $renderer->render($node, $scope, $state);
    }

    /**
     * Index a Blueprint's exact type/version/revision block locks.
     *
     * Duplicate type/version keys are retained as null ambiguity so strict
     * policy fails closed before any markup is returned.
     *
     * @param   \stdClass  $document  Decoded Blueprint.
     *
     * @return  array<string, BlockCoordinate|null>  Lock index.
     *
     * @throws  RenderException  When the lock shape is malformed.
     *
     * @since   0.2.0
     */
    private static function blockCoordinates(\stdClass $document): array
    {
        $dependencyLock = $document->dependencyLock ?? null;
        if ($dependencyLock === null) {
            return [];
        }
        $blocks = $dependencyLock instanceof \stdClass ? ($dependencyLock->blocks ?? null) : null;
        if (!is_array($blocks) || !array_is_list($blocks) || count($blocks) > 1000) {
            throw new RenderException('A Blueprint block dependency lock must be a list of at most 1000 entries.');
        }
        $coordinates = [];
        foreach ($blocks as $block) {
            if (!$block instanceof \stdClass
                || !is_string($block->type ?? null)
                || !is_string($block->version ?? null)
                || !is_string($block->revision ?? null)
            ) {
                throw new RenderException('A Blueprint block dependency coordinate is malformed.');
            }
            try {
                $coordinate = new BlockCoordinate($block->type, $block->version, $block->revision);
            } catch (\InvalidArgumentException $exception) {
                throw new RenderException('A Blueprint block dependency coordinate is invalid.', 0, $exception);
            }
            $key = $coordinate->versionKey();
            $coordinates[$key] = array_key_exists($key, $coordinates) ? null : $coordinate;
        }

        return $coordinates;
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
