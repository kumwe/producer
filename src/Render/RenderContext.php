<?php

/**
 * Host-supplied rendering authority.
 *
 * Everything a page needs beyond the composition itself — binding values,
 * media resolution, blob authority, scoped style intent — is handed in by
 * the host through this context. Producer holds no authority of its own and
 * fails closed whenever the host provides none.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render;

final class RenderContext
{
    public readonly ?\Closure $resolveBinding;

    public readonly ?\Closure $resolveMedia;

    /**
     * @param callable(\stdClass, string): mixed|null $resolveBinding
     *     resolves a node's port value; when omitted only static-value
     *     bindings stored on the node resolve
     * @param callable(\stdClass): (?ResolvedMedia)|null $resolveMedia
     *     resolves a media reference; when omitted every media block renders
     *     its unavailable fallback
     * @param array<string, mixed> $scopedStyles host style intent keyed by
     *     node id, each a scoped stylesheet accepted by
     *     {@see \Kumwe\Producer\Css\ScopedStylesheet::compile()}
     */
    public function __construct(
        public readonly bool $allowBlobMedia = false,
        ?callable $resolveBinding = null,
        ?callable $resolveMedia = null,
        public readonly array $scopedStyles = [],
    ) {
        $this->resolveBinding = $resolveBinding === null ? null : $resolveBinding(...);
        $this->resolveMedia = $resolveMedia === null ? null : $resolveMedia(...);
    }
}
