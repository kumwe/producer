<?php

/**
 * The artifact port, studio.port/artifact — the one required port.
 *
 * The host supplies versioned persistence with expected-revision writes;
 * Producer hands over only validated envelopes and returns only canonical
 * bytes. Each method receives the decoded operation argument and the
 * validated context, and refuses by throwing {@see HostRefusal} — a stale
 * expectedRevision is a conflict carrying the safe current revision.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\RequestContext;

/**
 * Versioned artifact persistence — the one port every conforming host
 * must serve.
 *
 * What the host receives: only calls the dispatcher has already
 * validated, cross-checked against the registry row, and had allowed by
 * the host's own {@see AuthorizationInterface}; the argument is
 * jsonValue-proven and passed through unjudged. What the host must
 * guarantee back: every method answers with a {@see HostResult} carrying
 * the operation's schema-shaped value and refuses only by throwing
 * {@see HostRefusal} with a taxonomy category and a non-disclosing
 * message — anything else thrown becomes an internal refusal. The three
 * mutations are concurrency-protected: their context always carries
 * expectedRevision, a stale one must be refused as conflict carrying the
 * safe current revision, and an accepted mutation must return the
 * advanced revision — the dispatcher fails closed as internal when it
 * does not.
 *
 * @since   0.1.0
 */
interface ArtifactPortInterface
{
    /**
     * studio.operation/artifact.dependencies — the references an artifact
     * depends on.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The dependency listing as the result value.
     *
     * @throws  HostRefusal  A taxonomy refusal — e.g. not-found for an
     *                       artifact outside the caller's view.
     *
     * @since   0.1.0
     */
    public function dependencies(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/artifact.load — one stored artifact document.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The artifact document as the result value.
     *
     * @throws  HostRefusal  A taxonomy refusal — e.g. not-found for an
     *                       artifact outside the caller's view.
     *
     * @since   0.1.0
     */
    public function load(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/artifact.publish — concurrency-protected; the
     * result carries the advanced revision and a null value.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context;
     *                                      expectedRevision is always
     *                                      present here.
     *
     * @return  HostResult  A null value plus the advanced revision.
     *
     * @throws  HostRefusal  conflict with the safe current revision when
     *                       expectedRevision is stale, or any other
     *                       taxonomy refusal.
     *
     * @since   0.1.0
     */
    public function publish(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/artifact.save — concurrency-protected; the result
     * carries the advanced revision and a null value.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context;
     *                                      expectedRevision is always
     *                                      present here.
     *
     * @return  HostResult  A null value plus the advanced revision.
     *
     * @throws  HostRefusal  conflict with the safe current revision when
     *                       expectedRevision is stale, or any other
     *                       taxonomy refusal.
     *
     * @since   0.1.0
     */
    public function save(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/artifact.unpublish — concurrency-protected; the
     * result carries the advanced revision and a null value.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context;
     *                                      expectedRevision is always
     *                                      present here.
     *
     * @return  HostResult  A null value plus the advanced revision.
     *
     * @throws  HostRefusal  conflict with the safe current revision when
     *                       expectedRevision is stale, or any other
     *                       taxonomy refusal.
     *
     * @since   0.1.0
     */
    public function unpublish(mixed $arguments, RequestContext $context): HostResult;
}
