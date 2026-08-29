<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * Exact dependency-lock coordinate to renderer mapping.
 *
 * A trusted host binds each executable implementation explicitly after
 * checking its owner and signed definition. Renderer code never declares
 * or widens its own authority. The default registry carries one coordinated
 * non-authoritative draft implementation for each pinned core
 * renderer-web block. Strict rendering uses only host-bound exact
 * coordinates and never treats a renderer release as a block revision.
 *
 * @since   0.1.0
 */
final class BlockRendererRegistry
{
    /**
     * Exact coordinate keys to immutable registrations.
     *
     * @var array<string, array{coordinate: BlockCoordinate, renderer: BlockRenderer}>
     *
     * @since   0.1.0
     */
    private array $registrations = [];

    /**
     * Type/version keys to non-authoritative core draft implementations.
     *
     * These are deliberately not {@see BlockCoordinate} registrations:
     * the renderer-web release does not define a block-definition
     * revision. A trusted host may discover one implementation here and
     * explicitly bind it to the real dependency lock through
     * {@see register()}.
     *
     * @var array<string, BlockRenderer>
     *
     * @since   0.2.0
     */
    private array $draftImplementations = [];

    /**
     * Bind one trusted renderer to one exact coordinate.
     *
     * Duplicate coordinates are refused rather than overwritten, so
     * registration order cannot silently change owner authority.
     *
     * @param   BlockCoordinate  $coordinate  Exact admitted lock.
     * @param   BlockRenderer    $renderer    Trusted implementation.
     *
     * @throws  RenderException  When the coordinate is already bound.
     *
     * @since   0.2.0
     */
    public function register(BlockCoordinate $coordinate, BlockRenderer $renderer): void
    {
        $key = $coordinate->key();
        if (isset($this->registrations[$key])) {
            throw new RenderException('A block renderer coordinate is already registered.');
        }
        $this->registrations[$key] = ['coordinate' => $coordinate, 'renderer' => $renderer];
    }

    /**
     * Whether one exact dependency-lock coordinate is executable.
     *
     * @param   BlockCoordinate  $coordinate  Candidate exact lock.
     *
     * @return  bool  True only for an exact registration.
     *
     * @since   0.2.0
     */
    public function supports(BlockCoordinate $coordinate): bool
    {
        return isset($this->registrations[$coordinate->key()]);
    }

    /**
     * Resolve one exact dependency-lock coordinate.
     *
     * @param   BlockCoordinate  $coordinate  Exact lock to resolve.
     *
     * @return  BlockRenderer|null  Trusted implementation, or null.
     *
     * @since   0.2.0
     */
    public function rendererFor(BlockCoordinate $coordinate): ?BlockRenderer
    {
        return $this->registrations[$coordinate->key()]['renderer'] ?? null;
    }

    /**
     * Discover the bounded core implementation used only by draft and
     * preview fallback.
     *
     * This lookup grants no execution authority for published output. A
     * trusted host that has verified the exact dependency-lock coordinate
     * must bind this implementation through {@see register()} before
     * selecting {@see RenderPolicy::RequireRegistered}.
     *
     * @param   string  $type     Block type.
     * @param   string  $version  Semantic block version.
     *
     * @return  BlockRenderer|null  Core draft implementation, or null.
     *
     * @since   0.2.0
     */
    public function draftRendererFor(string $type, string $version): ?BlockRenderer
    {
        return $this->draftImplementations[$type . "\0" . $version] ?? null;
    }

    /**
     * Every exact or draft implementation type in one deterministic order,
     * independent of registration order.
     *
     * @return  list<string>  Every registered type identifier, byte-sorted.
     * @since   0.1.0
     */
    public function types(): array
    {
        $types = [];
        foreach ($this->registrations as $registration) {
            $types[] = $registration['coordinate']->type;
        }
        foreach (array_keys($this->draftImplementations) as $key) {
            $types[] = explode("\0", $key, 2)[0];
        }
        $types = array_values(array_unique($types));
        sort($types, SORT_STRING);

        return $types;
    }

    /**
     * A registry covering the complete core production and layout catalog:
     * a non-authoritative draft renderer for all forty-five pinned
     * {@see BlockTypes} identifiers. Published rendering still requires a
     * trusted host to register every exact dependency-lock coordinate.
     *
     * @return  self  A fresh registry carrying the core catalog.
     * @since   0.1.0
     */
    public static function withCoreCatalog(): self
    {
        $registry = new self();
        foreach (
            [
                new Block\LayoutBlocks(),
                new Block\TextBlocks(),
                new Block\MediaBlocks(),
                new Block\InteractiveBlocks(),
                new Block\DataBlocks(),
            ] as $renderer
        ) {
            foreach ($renderer->types() as $type) {
                $registry->draftImplementations[$type . "\0" . '1.0.0'] = $renderer;
            }
        }

        return $registry;
    }
}
