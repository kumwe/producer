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

/**
 * What the idempotency ledger stores per scope key: the intent fingerprint
 * that was accepted and the result that answered it.
 *
 * On a recalled record, {@see Dispatcher} compares fingerprints: a match
 * replays the stored result without re-invoking the port; a mismatch is
 * refused as invalid-request, because an idempotency key names exactly one
 * intent for its lifetime.
 *
 * @since   0.1.0
 */
final class IdempotencyRecord
{
    /**
     * Binds one accepted outcome to its intent fingerprint.
     *
     * @param   string      $intentDigest  The canonical digest of the
     *                                     accepted request's argument and
     *                                     semantic context, computed by
     *                                     {@see Dispatcher}; never empty.
     * @param   HostResult  $result        The outcome to replay verbatim on
     *                                     a matching retry.
     *
     * @throws  \InvalidArgumentException  When the intent digest is empty.
     *
     * @since   0.1.0
     */
    public function __construct(
        public readonly string $intentDigest,
        public readonly HostResult $result,
    ) {
        if ($intentDigest === '') {
            throw new \InvalidArgumentException('An idempotency record needs a non-empty intent digest.');
        }
    }
}
