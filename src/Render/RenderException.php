<?php

/**
 * Fail-closed refusal raised by the renderer engine.
 *
 * The renderer only throws for structurally unusable input — an invalid node
 * identifier, a malformed safe-markup fragment — never for ordinary content
 * problems, which render as the reference renderer's bounded semantic
 * fallbacks instead.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Render;

final class RenderException extends \RuntimeException
{
}
