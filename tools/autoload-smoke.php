<?php

/**
 * Source-checkout entrypoint for the shipped package smoke.
 *
 * Composer metadata deliberately names only the root package script. This
 * wrapper keeps direct source-tree invocation concise.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

require dirname(__DIR__) . '/smoke.php';
