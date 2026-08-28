<?php

/**
 * The resource port, studio.port/resource.
 *
 * Bounded host resource search for reference pickers. Refusal is a thrown
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
 * Bounded host resource search for reference pickers, optional.
 *
 * What the host receives: only calls the dispatcher has already
 * validated, cross-checked against the registry row, and had allowed by
 * the host's own {@see AuthorizationInterface}. What the host must
 * guarantee back: a {@see HostResult} carrying a bounded search page
 * limited to the caller's view — search results are subject to the same
 * authority as direct reads — refusing only by throwing
 * {@see HostRefusal} with a taxonomy category and a non-disclosing
 * message.
 *
 * @since   0.1.0
 */
interface ResourcePortInterface
{
    /**
     * studio.operation/resource.search — a search page for a query.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The bounded search page, limited to the
     *                      caller's view.
     *
     * @throws  HostRefusal  A taxonomy refusal — e.g. validation-failed
     *                       for a query the host refuses.
     *
     * @since   0.1.0
     */
    public function search(mixed $arguments, RequestContext $context): HostResult;
}
