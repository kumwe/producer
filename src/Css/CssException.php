<?php

declare(strict_types=1);

namespace Kumwe\Producer\Css;

/**
 * Fail-closed refusal raised by the stylesheet generators.
 *
 * Anything outside the closed CSS vocabulary — an unknown property, an
 * unbounded value, a selector-shaped token — is refused rather than emitted.
 * The message names what was refused for humans; only the stable refusal
 * itself is contract.
 *
 * @since   0.1.0
 */
final class CssException extends \RuntimeException
{
}
