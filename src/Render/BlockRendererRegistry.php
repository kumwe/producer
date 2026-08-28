<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * The block type to renderer mapping.
 *
 * The default registry carries the complete pinned core catalog; hosts may
 * register additional renderers for their own block types. A type without a
 * renderer is not an error — the composition renderer emits the reference's
 * labeled, bounded fallback and the page still renders.
 *
 * @since   0.1.0
 */
final class BlockRendererRegistry
{
    /**
     * The current mapping, block type identifier to the renderer that
     * claimed it most recently.
     *
     * @var array<string, BlockRenderer>
     *
     * @since   0.1.0
     */
    private array $renderers = [];

    /**
     * Register a renderer for every type it claims. A later registration
     * for a type already claimed wins, which is how a host overrides a
     * core-catalog renderer with its own.
     *
     * @param   BlockRenderer  $renderer  The renderer to register.
     * @since   0.1.0
     */
    public function register(BlockRenderer $renderer): void
    {
        foreach ($renderer->types() as $type) {
            $this->renderers[$type] = $renderer;
        }
    }

    /**
     * The renderer registered for a block type, or null when the type is
     * unregistered — the caller then renders the bounded unknown-type
     * fallback instead of failing.
     *
     * @param   string  $type  The block type identifier to look up.
     * @return  ?BlockRenderer  The registered renderer, or null.
     * @since   0.1.0
     */
    public function rendererFor(string $type): ?BlockRenderer
    {
        return $this->renderers[$type] ?? null;
    }

    /**
     * Every registered block type in one deterministic order, independent
     * of registration order.
     *
     * @return  list<string>  Every registered type identifier, byte-sorted.
     * @since   0.1.0
     */
    public function types(): array
    {
        $types = array_keys($this->renderers);
        sort($types, SORT_STRING);

        return $types;
    }

    /**
     * A registry covering the complete core production and layout catalog:
     * a renderer for all forty-five pinned {@see BlockTypes} identifiers,
     * ready for hosts to extend or override with {@see self::register()}.
     *
     * @return  self  A fresh registry carrying the core catalog.
     * @since   0.1.0
     */
    public static function withCoreCatalog(): self
    {
        $registry = new self();
        $registry->register(new Block\LayoutBlocks());
        $registry->register(new Block\TextBlocks());
        $registry->register(new Block\MediaBlocks());
        $registry->register(new Block\InteractiveBlocks());
        $registry->register(new Block\DataBlocks());

        return $registry;
    }
}
