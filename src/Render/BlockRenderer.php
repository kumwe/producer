<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * A renderer for one or more block types of the catalog.
 *
 * Implementations produce the inner semantic HTML for a node; the
 * composition renderer owns the wrapper element, the scope, and the
 * presentation attributes. Implementations must route every dynamic value
 * through {@see SafeMarkup}.
 *
 * @since   0.1.0
 */
interface BlockRenderer
{
    /**
     * The block types this renderer claims. Stable for the life of the
     * instance: the registry reads it once, at registration.
     *
     * @return  list<string>  The block type identifiers this renderer serves.
     * @since   0.1.0
     */
    public function types(): array;

    /**
     * Produce the inner semantic HTML for one node of a claimed type. The
     * result must be fully escaped markup — every dynamic value routed
     * through {@see SafeMarkup} — and must degrade to usable no-JavaScript
     * markup; a content problem renders the block's bounded semantic
     * fallback rather than throwing.
     *
     * @param   \stdClass    $node   The decoded Blueprint node to render.
     * @param   string       $scope  The node's CSS-safe scope token.
     * @param   RenderState  $state  Per-render accumulation and engine
     *     services (child slots, bindings, vetted media).
     * @return  string  Escaped inner HTML for the node.
     * @since   0.1.0
     */
    public function render(\stdClass $node, string $scope, RenderState $state): string;
}
