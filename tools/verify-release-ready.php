<?php

/**
 * Refuse Producer publication while its exact Studio pin has open blockers.
 *
 * Contract verification deliberately succeeds for a complete, reviewable
 * beta pin even when publication evidence is incomplete. The release workflow
 * invokes this narrower gate immediately before any tag can be created.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

$path = dirname(__DIR__) . '/resources/studio-contract/PIN.json';
try {
    $pin = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, 'Producer release gate could not read PIN.json: ' . $error->getMessage() . "\n");
    exit(1);
}

$readiness = is_array($pin) && is_array($pin['release_readiness'] ?? null)
    ? $pin['release_readiness']
    : [];
$status = $readiness['status'] ?? null;
$blockers = $readiness['blockers'] ?? null;
if (!is_array($blockers) || !array_is_list($blockers)) {
    fwrite(STDERR, "Producer release gate found no ordered blocker decision.\n");
    exit(1);
}
foreach ($blockers as $blocker) {
    if (!is_string($blocker) || $blocker === '') {
        fwrite(STDERR, "Producer release gate found a malformed blocker.\n");
        exit(1);
    }
}

if ($status === 'blocked' && $blockers !== []) {
    fwrite(STDERR, "Producer release is blocked:\n - " . implode("\n - ", $blockers) . "\n");
    exit(1);
}
if ($status !== 'ready' || $blockers !== []) {
    fwrite(STDERR, "Producer release readiness is inconsistent; publication refused.\n");
    exit(1);
}

echo "Producer release pin is ready for publication.\n";
