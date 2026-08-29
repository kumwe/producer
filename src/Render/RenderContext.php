<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

use Kumwe\Producer\Error\ContractGrammar;

/**
 * Host-supplied rendering authority.
 *
 * Everything a page needs beyond the composition itself — binding values,
 * media resolution, blob authority, scoped style intent, and an optional
 * exact preview marker inventory — is handed in by the host through this
 * context. Producer holds no authority of its own and fails closed whenever
 * the host provides none. Immutable once constructed.
 *
 * @since   0.1.0
 */
final class RenderContext
{
    /**
     * Node-keyed inverse of the admitted marker inventory.
     *
     * @var array<string, string>
     *
     * @since   0.2.0
     */
    private readonly array $previewMarkersByNode;

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
     * @param   callable(\stdClass, string): (BindingResolution|mixed)|null  $resolveBinding
     *     Resolves a node port. Return {@see BindingResolution} to preserve
     *     hidden/unavailable/available-null; a raw value is normalized to
     *     available. When omitted only static-value bindings resolve.
     * @param   callable(\stdClass): (?ResolvedMedia)|null  $resolveMedia
     *     Resolves a media reference; when omitted every media block
     *     renders its unavailable fallback.
     * @param   array<string, mixed>  $scopedStyles  Host style intent keyed
     *     by node id, each a scoped stylesheet accepted by
     *     {@see \Kumwe\Producer\Css\ScopedStylesheet::compile()}.
     * @param   array<string, string>|null  $previewMarkerMap  Exact marker to
     *     node-id inventory for an authoring preview, or null for public
     *     marker-free rendering. Producer admits only the pinned preview
     *     marker grammar and emits only its fixed wrapper attribute.
     * @param   RenderPolicy  $policy  Bounded fallback for draft/preview,
     *     or exact registered-coordinate enforcement for published output.
     * @throws  \InvalidArgumentException  When the preview inventory is
     *     malformed, duplicated by node, or exceeds the pinned bound.
     * @since   0.1.0
     */
    public function __construct(
        public readonly bool $allowBlobMedia = false,
        ?callable $resolveBinding = null,
        ?callable $resolveMedia = null,
        public readonly array $scopedStyles = [],
        public readonly ?array $previewMarkerMap = null,
        public readonly RenderPolicy $policy = RenderPolicy::Fallback,
    ) {
        $this->resolveBinding = $resolveBinding === null ? null : $resolveBinding(...);
        $this->resolveMedia = $resolveMedia === null ? null : $resolveMedia(...);
        $byNode = [];
        if ($previewMarkerMap !== null) {
            if (count($previewMarkerMap) > 100000) {
                throw new \InvalidArgumentException('A preview marker inventory carries at most 100000 entries.');
            }
            foreach ($previewMarkerMap as $marker => $nodeId) {
                if (!is_string($marker)
                    || !is_string($nodeId)
                    || preg_match(
                        '%^studio\.preview/node/[0-9a-f]{64}/(?:0|[1-9][0-9]{0,4})$%D',
                        $marker,
                    ) !== 1
                    || !ContractGrammar::isStableId($nodeId)
                ) {
                    throw new \InvalidArgumentException('A preview marker inventory entry is out of contract.');
                }
                $key = "node\0" . $nodeId;
                if (isset($byNode[$key])) {
                    throw new \InvalidArgumentException('A preview marker inventory repeats a node identity.');
                }
                $byNode[$key] = $marker;
            }
        }
        $this->previewMarkersByNode = $byNode;
    }

    /**
     * Resolve one node's exact preview marker from the admitted inventory.
     *
     * @param   string  $nodeId  Stable Blueprint node identity.
     *
     * @return  string|null  The exact marker, or null when absent/public.
     *
     * @since   0.2.0
     */
    public function previewMarkerFor(string $nodeId): ?string
    {
        return $this->previewMarkersByNode["node\0" . $nodeId] ?? null;
    }
}
