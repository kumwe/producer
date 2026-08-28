<?php

/**
 * Mutable per-render accumulation shared by the block renderers.
 *
 * Collects generated per-node CSS and enhancement requests in document
 * order, and offers block renderers the engine services they need: child
 * slot rendering, binding resolution, and vetted media resolution.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render;

final class RenderState
{
    /** @var list<string> */
    public array $css = [];

    /** @var list<Enhancement> */
    public array $enhancements = [];

    public function __construct(
        public readonly RenderContext $context,
        private readonly CompositionRenderer $renderer,
    ) {
    }

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
     * @param list<\stdClass> $nodes
     */
    public function renderNodes(array $nodes): string
    {
        return $this->renderer->renderNodes($nodes, $this);
    }

    public function renderChildren(\stdClass $node, string $slot): string
    {
        return $this->renderNodes($this->slotNodes($node, $slot));
    }

    /**
     * @return list<\stdClass>
     */
    public function slotNodes(\stdClass $node, string $slot): array
    {
        $children = $node->slots->{$slot} ?? null;

        return is_array($children) ? $children : [];
    }

    /**
     * The value bound to a node port: the host's resolver when it provides
     * one, otherwise only the static value stored on the node's binding.
     */
    public function bindingValue(\stdClass $node, string $port): mixed
    {
        $resolver = $this->context->resolveBinding;
        if ($resolver !== null) {
            return $resolver($node, $port);
        }
        $binding = $node->bindings->{$port} ?? null;

        return ($binding->source->kind ?? null) === 'static-value'
            ? ($binding->source->value ?? null)
            : null;
    }

    /**
     * Resolve a media-reference value through the host and vet the resolved
     * URL. Anything unresolvable or unsafe is simply unavailable.
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
