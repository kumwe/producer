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

interface ArtifactPortInterface
{
    /**
     * studio.operation/artifact.dependencies — the references an artifact
     * depends on.
     *
     * @throws HostRefusal
     */
    public function dependencies(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/artifact.load — one stored artifact document.
     *
     * @throws HostRefusal
     */
    public function load(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/artifact.publish — concurrency-protected; the
     * result carries the advanced revision and a null value.
     *
     * @throws HostRefusal
     */
    public function publish(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/artifact.save — concurrency-protected; the result
     * carries the advanced revision and a null value.
     *
     * @throws HostRefusal
     */
    public function save(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/artifact.unpublish — concurrency-protected; the
     * result carries the advanced revision and a null value.
     *
     * @throws HostRefusal
     */
    public function unpublish(mixed $arguments, RequestContext $context): HostResult;
}
