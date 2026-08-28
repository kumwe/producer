<?php

/**
 * One accepted mutation outcome held for idempotent replay.
 *
 * The intent digest is the canonical fingerprint of what the caller asked
 * for — the argument plus the semantic context fields (expectedRevision,
 * locale, protocolVersion), absent optionals omitted — computed by
 * {@see Dispatcher}. A retry whose fingerprint matches replays the stored
 * result; a retry that reuses the key with a different fingerprint is
 * refused, exactly as the pinned host sequence vectors require.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire;

final class IdempotencyRecord
{
    public function __construct(
        public readonly string $intentDigest,
        public readonly HostResult $result,
    ) {
        if ($intentDigest === '') {
            throw new \InvalidArgumentException('An idempotency record needs a non-empty intent digest.');
        }
    }
}
