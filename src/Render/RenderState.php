<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * Mutable per-render accumulation shared by the block renderers.
 *
 * Collects generated per-node CSS and enhancement requests in document
 * order, and offers block renderers the engine services they need: child
 * slot rendering, binding resolution, and vetted media resolution. One
 * instance lives for exactly one render and is never reused.
 *
 * @since   0.1.0
 */
final class RenderState
{
    /**
     * Compiled per-node scoped stylesheets in encounter (document) order;
     * the composition renderer prepends the base stylesheet and drops
     * empty entries when assembling the result.
     *
     * @var list<string>
     *
     * @since   0.1.0
     */
    public array $css = [];

    /**
     * Every enhancement request so far, in document order — the order the
     * conformance corpus fixes.
     *
     * @var list<Enhancement>
     *
     * @since   0.1.0
     */
    public array $enhancements = [];

    /**
     * Preview markers consumed by rendered nodes, for exact inventory
     * reconciliation after the walk.
     *
     * @var array<string, true>
     *
     * @since   0.2.0
     */
    public array $previewMarkers = [];

    /**
     * Nodes suppressed by a hidden binding resolution.
     *
     * @var array<string, true>
     *
     * @since   0.2.0
     */
    private array $hiddenNodes = [];

    /**
     * @param   RenderContext        $context   The host authority for this
     *     render.
     * @param   CompositionRenderer  $renderer  The engine that walks child
     *     nodes on this state's behalf.
     * @param   array<string, BlockCoordinate|null>  $blockCoordinates  Exact
     *     type/version locks from the Blueprint; null marks ambiguity.
     * @since   0.1.0
     */
    public function __construct(
        public readonly RenderContext $context,
        private readonly CompositionRenderer $renderer,
        public readonly array $blockCoordinates = [],
    ) {
    }

    /**
     * Record one enhancement request for a node, in document order. The
     * node's stored id is captured as-is; nothing is deduplicated here.
     *
     * @param   string     $kind   The behavior name from the reference
     *     runtime's closed vocabulary.
     * @param   \stdClass  $node   The requesting node.
     * @param   string     $scope  The node's CSS-safe scope token.
     * @param   array<string, mixed>  $details  Behavior-specific
     *     configuration for the runtime.
     * @since   0.1.0
     */
    public function enhance(string $kind, \stdClass $node, string $scope, array $details = []): void
    {
        $this->enhancements[] = new Enhancement(
            $kind,
            Properties::stringValue($node->id ?? null),
            $scope,
            $details
        );
    }

    /**
     * Render child nodes through the engine, accumulating their CSS and
     * enhancements on this state.
     *
     * @param   list<\stdClass>  $nodes  Decoded Blueprint nodes.
     * @return  string  The nodes' escaped markup, in document order.
     * @throws  RenderException  When a node is structurally unusable, as in
     *     {@see CompositionRenderer::renderNodes()}.
     * @since   0.1.0
     */
    public function renderNodes(array $nodes): string
    {
        return $this->renderer->renderNodes($nodes, $this);
    }

    /**
     * Render every node in one named slot of a node; a missing or
     * malformed slot renders as the empty string, never an error.
     *
     * @param   \stdClass  $node  The parent node.
     * @param   string     $slot  The slot name.
     * @return  string  The slot's escaped markup, in document order.
     * @throws  RenderException  When a child node is structurally unusable.
     * @since   0.1.0
     */
    public function renderChildren(\stdClass $node, string $slot): string
    {
        return $this->renderNodes($this->slotNodes($node, $slot));
    }

    /**
     * The stored children of one named slot; a missing or non-array slot
     * yields the empty list.
     *
     * @param   \stdClass  $node  The parent node.
     * @param   string     $slot  The slot name.
     * @return  list<\stdClass>  The slot's stored child nodes.
     * @since   0.1.0
     */
    public function slotNodes(\stdClass $node, string $slot): array
    {
        $children = $node->slots->{$slot} ?? null;
        if (!is_array($children) || !array_is_list($children)) {
            return [];
        }
        $nodes = [];
        foreach ($children as $child) {
            if (!$child instanceof \stdClass) {
                throw new RenderException('Blueprint nodes must be decoded objects.');
            }
            $nodes[] = $child;
        }

        return $nodes;
    }

    /**
     * Resolve a node port without conflating null, hidden, and unavailable.
     *
     * @param   \stdClass  $node  The bound node.
     * @param   string     $port  The port name.
     * @return  BindingResolution  Closed trusted outcome.
     *
     * @since   0.2.0
     */
    public function bindingResolution(\stdClass $node, string $port): BindingResolution
    {
        $resolver = $this->context->resolveBinding;
        if ($resolver !== null) {
            $candidate = $resolver($node, $port);
            $resolution = $candidate instanceof BindingResolution
                ? $candidate
                : BindingResolution::available($candidate);
        } else {
            $binding = $node->bindings->{$port} ?? null;
            $source = $binding instanceof \stdClass ? ($binding->source ?? null) : null;
            $resolution = $source instanceof \stdClass
                && ($source->kind ?? null) === 'static-value'
                && property_exists($source, 'value')
                ? BindingResolution::available($source->value)
                : BindingResolution::unavailable();
        }
        if ($resolution->isHidden()) {
            $id = Properties::stringValue($node->id ?? null);
            $this->hiddenNodes["node\0" . $id] = true;
        }

        return $resolution;
    }

    /**
     * The available value bound to a node port, or null for hidden and
     * unavailable outcomes.
     *
     * Call {@see bindingResolution()} when available null must remain
     * distinct. This convenience method preserves the renderer-web
     * catalog's value-oriented block API.
     *
     * @param   \stdClass  $node  The bound node.
     * @param   string     $port  The port name.
     *
     * @return  mixed  Available value, or null.
     *
     * @since   0.1.0
     */
    public function bindingValue(\stdClass $node, string $port): mixed
    {
        return $this->bindingResolution($node, $port)->value();
    }

    /**
     * Whether binding policy has hidden one node during rendering.
     *
     * @param   string  $nodeId  Stable Blueprint node identity.
     *
     * @return  bool  True when at least one binding resolved hidden.
     *
     * @since   0.2.0
     */
    public function isNodeHidden(string $nodeId): bool
    {
        return isset($this->hiddenNodes["node\0" . $nodeId]);
    }

    /**
     * Resolve a media-reference value through the host and vet the
     * resolved URL through {@see SafeMarkup::safeMediaUrl()} under the
     * context's blob authority. Anything unresolvable or unsafe — a
     * non-reference value, no host resolver, a refused URL — is simply
     * unavailable, and the owning block renders its fallback.
     *
     * @param   mixed  $value  The bound value; only a media-reference
     *     object with a string assetId can resolve.
     * @return  ?ResolvedMedia  The vetted media, or null when unavailable.
     * @since   0.1.0
     */
    public function resolvedMedia(mixed $value): ?ResolvedMedia
    {
        if (
            !$value instanceof \stdClass
            || ($value->kind ?? null) !== 'media-reference'
            || !is_string($value->assetId ?? null)
        ) {
            return null;
        }
        $resolver = $this->context->resolveMedia;
        if ($resolver === null) {
            return null;
        }
        $media = $resolver($value);
        if (!$media instanceof ResolvedMedia) {
            return null;
        }
        $safe = SafeMarkup::safeMediaUrl($media, $this->context->allowBlobMedia);

        return $safe === null ? null : $media->withSrc($safe);
    }
}
