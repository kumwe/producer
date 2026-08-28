<?php

/**
 * The preview port, studio.port/preview.
 *
 * Draft rendering for the authoring surface. Refusal is a thrown
 * {@see HostRefusal}; a render superseded by cancellation is cancelled,
 * retryable false, per the pinned sequence vectors.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\RequestContext;

/**
 * Draft rendering for the authoring surface, optional.
 *
 * What the host receives: only calls the dispatcher has already
 * validated, cross-checked against the registry row, and had allowed by
 * the host's own {@see AuthorizationInterface}. What the host must
 * guarantee back: a {@see HostResult} carrying the schema-shaped
 * outcome, refusing only by throwing {@see HostRefusal} with a taxonomy
 * category and a non-disclosing message — a render superseded by
 * cancellation is cancelled, retryable false, per the pinned sequence
 * vectors.
 *
 * @since   0.1.0
 */
interface PreviewPortInterface
{
    /**
     * studio.operation/preview.cancel — cancel outstanding renders for a
     * draft digest.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The cancellation acknowledgement.
     *
     * @throws  HostRefusal  A taxonomy refusal.
     *
     * @since   0.1.0
     */
    public function cancel(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/preview.render — render one draft; the result is
     * the rendered payload.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The rendered payload.
     *
     * @throws  HostRefusal  A taxonomy refusal — cancelled, retryable
     *                       false, when the render was superseded by
     *                       cancellation.
     *
     * @since   0.1.0
     */
    public function render(mixed $arguments, RequestContext $context): HostResult;
}
