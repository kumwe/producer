<?php

/**
 * Prove a clean Composer install can autoload the complete reviewed API.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Composer autoload is absent; run composer install first.\n");
    exit(1);
}
require $autoload;

$snapshot = json_decode((string) file_get_contents($root . '/resources/public-api.json'), true);
if (!is_array($snapshot)
    || ($snapshot['schema'] ?? null) !== 2
    || !is_array($snapshot['types'] ?? null)
) {
    fwrite(STDERR, "The public API manifest is malformed.\n");
    exit(1);
}
$types = $snapshot['types'];
$errors = [];
foreach ($types as $type => $entry) {
    $kind = is_array($entry) ? ($entry['kind'] ?? null) : null;
    if (!is_string($type) || !is_string($kind)) {
        $errors[] = 'Malformed public API snapshot entry.';
        continue;
    }
    $loaded = match ($kind) {
        'class' => class_exists($type),
        'interface' => interface_exists($type),
        'enum' => enum_exists($type),
        default => false,
    };
    if (!$loaded) {
        $errors[] = $type . ' did not autoload as a ' . $kind . '.';
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Composer autoload smoke failed:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo 'Composer autoload verified: ' . count($types) . " public types.\n";
