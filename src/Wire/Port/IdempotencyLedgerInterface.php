<?php

/**
 * The host's durable idempotency ledger for mutation retries.
 *
 * Producer computes the deterministic identity — the scope key digests
 * (idempotencyKey, operationId, resourceContextKey, sessionGeneration),
 * the record's intent digest fingerprints the argument and semantic
 * context, per the pinned host sequence vectors — and drives the replay
 * decision; the host supplies only durable storage, which is why this
 * lives behind an interface: Producer owns no storage.
 *
 * `record` is called after the mutation was accepted. A host that needs
 * the ledger write atomic with the mutation itself should persist inside
 * its port implementation and make `record` idempotent for the same key;
 * in-flight coalescing of concurrent retries is likewise the host's
 * concurrency control, not Producer's.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Wire\IdempotencyRecord;

interface IdempotencyLedgerInterface
{
    /**
     * The outcome previously accepted under this scope key, or null when
     * the key is unseen.
     */
    public function recall(string $scopeKey): ?IdempotencyRecord;

    /**
     * Durably associate the accepted outcome with the scope key.
     */
    public function record(string $scopeKey, IdempotencyRecord $record): void;
}
