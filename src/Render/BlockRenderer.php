<?php

/**
 * A renderer for one or more block types of the catalog.
 *
 * Implementations produce the inner semantic HTML for a node; the
 * composition renderer owns the wrapper element, the scope, and the
 * presentation attributes. Implementations must route every dynamic value
 * through {@see SafeMarkup}.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render;

interface BlockRenderer
{
    /**
     * @return list<string> the block type identifiers this renderer serves
     */
    public function types(): array;

    public function render(\stdClass $node, string $scope, RenderState $state): string;
}
