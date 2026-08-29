<?php

/**
 * The host-atomic boundary for every mutation and keyed replay.
 *
 * Producer computes optional replay coordinates; the host owns the
 * transaction, trusted scope, mutation storage, audit, and protected
 * outcome persistence. All mutations cross this boundary so an unkeyed
 * request cannot accidentally bypass the App's existing transaction and
 * audit guarantees.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Error\HostError;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\MutationOutcome;
use Kumwe\Producer\Wire\Operation;
use Kumwe\Producer\Wire\RequestEnvelope;

/**
 * Host-owned atomic execution, audit, and optional replay storage.
 *
 * {@see execute()} always runs the mutation, audit, and authoritative
 * storage effects in one host transaction. For a keyed request, it must
 * additionally namespace the supplied scope with trusted actor and
 * browser-session identity, serialize callers for that complete scope,
 * and return a completed replay without calling the mutation. For an
 * unkeyed request both digests are null and replay is disabled, but the
 * transaction and audit guarantees are identical.
 *
 * Idempotency storage is host-owned. The host may encrypt an outcome,
 * store a redacted projection, or persist a handle to protected material.
 * It must never persist a plaintext capability merely because the wire
 * result contains one. On replay it integrity-checks and rehydrates the
 * exact logical {@see HostResult} or {@see HostError} before returning a
 * {@see MutationOutcome}; this interface imposes no at-rest byte format.
 *
 * @since   0.2.0
 */
interface MutationBoundaryInterface
{
    /**
     * Commit one mutation outcome or return its completed keyed replay.
     *
     * Scope and intent are either both canonical digests for a keyed
     * mutation, or both null for an unkeyed mutation. The callback must run
     * inside the same transaction that commits its state and audit. A
     * returned {@see HostError} is an explicitly committed refusal; an
     * ordinary thrown refusal or any failure must roll back and escape.
     *
     * @param   Operation                           $operation    Closed
     *                                                            registry row.
     * @param   RequestEnvelope                     $request      Validated
     *                                                            request.
     * @param   string|null                         $scopeKey     Producer replay
     *                                                            scope digest,
     *                                                            or null.
     * @param   string|null                         $intentDigest Producer request
     *                                                            intent digest,
     *                                                            or null.
     * @param   callable(): (HostResult|HostError)  $mutation     Mutation to
     *                                                            execute once.
     *
     * @return  MutationOutcome  Fresh committed outcome or exact logical
     *                           keyed replay.
     *
     * @since   0.2.0
     */
    public function execute(
        Operation $operation,
        RequestEnvelope $request,
        ?string $scopeKey,
        ?string $intentDigest,
        callable $mutation,
    ): MutationOutcome;
}
