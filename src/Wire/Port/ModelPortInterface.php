<?php

/**
 * The model port, studio.port/model.
 *
 * Read access to content model documents. Refusal is a thrown
 * {@see HostRefusal}.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\RequestContext;

/**
 * Read access to the host's content model documents, optional.
 *
 * What the host receives: only calls the dispatcher has already
 * validated, cross-checked against the registry row, and had allowed by
 * the host's own {@see AuthorizationInterface}. What the host must
 * guarantee back: a {@see HostResult} carrying the schema-shaped content
 * model documents, refusing only by throwing {@see HostRefusal} with a
 * taxonomy category and a non-disclosing message. Both operations are
 * reads; neither mutates anything.
 *
 * @since   0.1.0
 */
interface ModelPortInterface
{
    /**
     * studio.operation/model.get — one content model document.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The content model document.
     *
     * @throws  HostRefusal  A taxonomy refusal — e.g. not-found for a
     *                       model outside the caller's view.
     *
     * @since   0.1.0
     */
    public function get(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/model.list — the content model documents in scope;
     * takes only the envelope.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The content model documents in the caller's
     *                      scope.
     *
     * @throws  HostRefusal  A taxonomy refusal.
     *
     * @since   0.1.0
     */
    public function list(mixed $arguments, RequestContext $context): HostResult;
}
