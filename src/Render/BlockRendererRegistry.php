<?php

/**
 * The block type to renderer mapping.
 *
 * The default registry carries the complete pinned core catalog; hosts may
 * register additional renderers for their own block types. A type without a
 * renderer is not an error — the composition renderer emits the reference's
 * labeled, bounded fallback and the page still renders.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render;

final class BlockRendererRegistry
{
    /** @var array<string, BlockRenderer> */
    private array $renderers = [];

    public function register(BlockRenderer $renderer): void
    {
        foreach ($renderer->types() as $type) {
            $this->renderers[$type] = $renderer;
        }
    }

    public function rendererFor(string $type): ?BlockRenderer
    {
        return $this->renderers[$type] ?? null;
    }

    /**
     * @return list<string> every registered block type identifier, sorted
     */
    public function types(): array
    {
        $types = array_keys($this->renderers);
        sort($types, SORT_STRING);

        return $types;
    }

    /**
     * The registry covering the complete core production and layout catalog.
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
