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

/**
 * Durable storage for keyed-mutation outcomes; the replay decision itself
 * stays in the dispatcher.
 *
 * What the host receives: opaque, deterministic scope keys computed by
 * Producer — it never parses or interprets them. What the host must
 * guarantee: {@see record()} persists durably before it returns, and
 * {@see recall()} returns exactly what was recorded under the key or null
 * for an unseen one. A ledger that cannot answer must throw rather than
 * guess — the dispatcher then refuses as internal, because a mutation
 * whose replay cannot be ruled out must not run.
 *
 * @since   0.1.0
 */
interface IdempotencyLedgerInterface
{
    /**
     * The outcome previously accepted under this scope key, or null when
     * the key is unseen.
     *
     * @param   string  $scopeKey  The canonical scope key digest, opaque
     *                             to the host.
     *
     * @return  IdempotencyRecord|null  The stored record, exactly as
     *                                  recorded, or null.
     *
     * @since   0.1.0
     */
    public function recall(string $scopeKey): ?IdempotencyRecord;

    /**
     * Durably associate the accepted outcome with the scope key.
     *
     * @param   string             $scopeKey  The canonical scope key
     *                                        digest, opaque to the host.
     * @param   IdempotencyRecord  $record    The intent digest and result
     *                                        to hold for replay.
     *
     * @since   0.1.0
     */
    public function record(string $scopeKey, IdempotencyRecord $record): void;
}
