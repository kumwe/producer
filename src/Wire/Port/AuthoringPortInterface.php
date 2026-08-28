<?php

/**
 * The contextual authoring port, studio.port/authoring.
 *
 * Host-authoritative coordinated authoring: resolve a declared target,
 * list reusable content types, start a resource-bound session, plan a
 * save, and execute exactly one of the three explicit save outcomes.
 * The port is additive to the artifact port — no method may be an
 * undocumented sequence of artifact.save calls. Arguments and results are
 * the documents of authoring-target, authoring-session, authoring-save,
 * and reusable-content-type in the vendored schemas. Refusal is a thrown
 * {@see HostRefusal}.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\RequestContext;

interface AuthoringPortInterface
{
    /**
     * studio.operation/authoring.list-types — a reusable-content-type
     * list page for a list query.
     *
     * @throws HostRefusal
     */
    public function listTypes(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/authoring.plan-save — the host's save plan for a
     * save intent, with consequences and confirmation requirement.
     *
     * @throws HostRefusal
     */
    public function planSave(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/authoring.resolve-target — the resolution of a
     * declared authoring target for a resource context.
     *
     * @throws HostRefusal
     */
    public function resolveTarget(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/authoring.save-as-new-type — execute a planned
     * save-as-new-type; the result is an authoring-save-result.
     *
     * @throws HostRefusal
     */
    public function saveAsNewType(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/authoring.save-item — execute a planned save-item;
     * the result is an authoring-save-result.
     *
     * @throws HostRefusal
     */
    public function saveItem(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/authoring.save-new-type-version — execute a
     * planned save-new-type-version; the result is an
     * authoring-save-result.
     *
     * @throws HostRefusal
     */
    public function saveNewTypeVersion(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/authoring.start — open a session; the result is an
     * authoring-session snapshot.
     *
     * @throws HostRefusal
     */
    public function start(mixed $arguments, RequestContext $context): HostResult;
}
