<?php

/**
 * The telemetry port, studio.port/telemetry.
 *
 * Named events with primitive attributes, under the host's privacy
 * policy. Refusal is a thrown {@see HostRefusal}; a non-primitive
 * attribute is validation-failed per the pinned host vectors.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\RequestContext;

/**
 * Named telemetry events under the host's privacy policy, optional.
 *
 * What the host receives: only calls the dispatcher has already
 * validated, cross-checked against the registry row, and had allowed by
 * the host's own {@see AuthorizationInterface}. What the host must
 * guarantee back: acceptance recorded under its own privacy policy and a
 * {@see HostResult} with a null value, refusing only by throwing
 * {@see HostRefusal} with a taxonomy category and a non-disclosing
 * message — a non-primitive attribute is validation-failed, per the
 * pinned host vectors. Emit is a mutation and may carry an idempotency
 * key; the dispatcher then replays its recorded outcome without
 * re-invoking the port.
 *
 * @since   0.1.0
 */
interface TelemetryPortInterface
{
    /**
     * studio.operation/telemetry.emit — record one telemetry event; the
     * result value is null.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  An explicit null value.
     *
     * @throws  HostRefusal  A taxonomy refusal — validation-failed for a
     *                       non-primitive attribute.
     *
     * @since   0.1.0
     */
    public function emit(mixed $arguments, RequestContext $context): HostResult;
}
