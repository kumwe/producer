<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * Host-supplied rendering authority.
 *
 * Everything a page needs beyond the composition itself — binding values,
 * media resolution, blob authority, scoped style intent — is handed in by
 * the host through this context. Producer holds no authority of its own and
 * fails closed whenever the host provides none. Immutable once constructed.
 *
 * @since   0.1.0
 */
final class RenderContext
{
    /**
     * The host's binding resolver as a closure, or null when the host
     * provided none and only static-value bindings resolve.
     *
     * @since   0.1.0
     */
    public readonly ?\Closure $resolveBinding;

    /**
     * The host's media resolver as a closure, or null when the host
     * provided none and every media block renders its unavailable fallback.
     *
     * @since   0.1.0
     */
    public readonly ?\Closure $resolveMedia;

    /**
     * @param   bool  $allowBlobMedia  Explicit host authority for
     *     non-executable blob: media URLs; false keeps the https-and-
     *     relative allowlist alone in force.
     * @param   callable(\stdClass, string): mixed|null  $resolveBinding
     *     Resolves a node's port value; when omitted only static-value
     *     bindings stored on the node resolve.
     * @param   callable(\stdClass): (?ResolvedMedia)|null  $resolveMedia
     *     Resolves a media reference; when omitted every media block
     *     renders its unavailable fallback.
     * @param   array<string, mixed>  $scopedStyles  Host style intent keyed
     *     by node id, each a scoped stylesheet accepted by
     *     {@see \Kumwe\Producer\Css\ScopedStylesheet::compile()}.
     * @since   0.1.0
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
