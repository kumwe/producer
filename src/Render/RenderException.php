<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

/**
 * Fail-closed refusal raised by the renderer engine.
 *
 * The renderer only throws for structurally unusable input — an invalid node
 * identifier, a malformed safe-markup fragment — never for ordinary content
 * problems, which render as the reference renderer's bounded semantic
 * fallbacks instead. The message is for humans; only the refusal itself is
 * contract.
 *
 * @since   0.1.0
 */
final class RenderException extends \RuntimeException
{
}
