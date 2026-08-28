<?php

/**
 * Fail-closed refusal raised by the stylesheet generators.
 *
 * Anything outside the closed CSS vocabulary — an unknown property, an
 * unbounded value, a selector-shaped token — is refused rather than emitted.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Css;

final class CssException extends \RuntimeException
{
}
