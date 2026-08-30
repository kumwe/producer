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

/**
 * Host-authoritative coordinated authoring, optional and additive to the
 * artifact port.
 *
 * What the host receives: only calls the dispatcher has already
 * validated, cross-checked against the registry row, and had allowed by
 * the host's own {@see AuthorizationInterface}; arguments are
 * jsonValue-proven documents of the vendored authoring schemas
 * (authoring-target, authoring-session, authoring-save,
 * reusable-content-type). What the host must guarantee back: every
 * method answers with a {@see HostResult} carrying the operation's
 * schema-shaped document, refuses only by throwing {@see HostRefusal}
 * with a taxonomy category and a non-disclosing message, and implements
 * each save outcome as its own authoritative operation — never as an
 * undocumented sequence of artifact.save calls. The four authoring
 * mutations may carry an idempotency key; the dispatcher then replays
 * their recorded outcomes without re-invoking the port.
 *
 * @since   0.1.0
 */
interface AuthoringPortInterface
{
    /**
     * studio.operation/authoring.list-types — reusable content types.
     *
     * @param mixed          $arguments Decoded, jsonValue-proven argument.
     * @param RequestContext $context   Validated envelope context.
     *
     * @throws HostRefusal A taxonomy refusal.
     *
     * @since 0.1.0
     */
    public function listTypes(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/authoring.plan-save — host-derived save plan.
     *
     * @param mixed          $arguments Decoded, jsonValue-proven argument.
     * @param RequestContext $context   Validated envelope context.
     *
     * @throws HostRefusal A taxonomy refusal.
     *
     * @since 0.1.0
     */
    public function planSave(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/authoring.resolve-target — authorized target.
     *
     * @param mixed          $arguments Decoded, jsonValue-proven argument.
     * @param RequestContext $context   Validated envelope context.
     *
     * @throws HostRefusal A taxonomy refusal.
     *
     * @since 0.1.0
     */
    public function resolveTarget(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/authoring.save-as-new-type — create a reusable type.
     *
     * @param mixed          $arguments Decoded, jsonValue-proven argument.
     * @param RequestContext $context   Validated envelope context.
     *
     * @throws HostRefusal A taxonomy refusal.
     *
     * @since 0.1.0
     */
    public function saveAsNewType(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/authoring.save-item — commit the Entry transaction.
     *
     * @param mixed          $arguments Decoded, jsonValue-proven argument.
     * @param RequestContext $context   Validated envelope context.
     *
     * @throws HostRefusal A taxonomy refusal.
     *
     * @since 0.1.0
     */
    public function saveItem(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/authoring.save-new-type-version — create a successor.
     *
     * @param mixed          $arguments Decoded, jsonValue-proven argument.
     * @param RequestContext $context   Validated envelope context.
     *
     * @throws HostRefusal A taxonomy refusal.
     *
     * @since 0.1.0
     */
    public function saveNewTypeVersion(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/authoring.start — open a contextual session.
     *
     * @param mixed          $arguments Decoded, jsonValue-proven argument.
     * @param RequestContext $context   Validated envelope context.
     *
     * @throws HostRefusal A taxonomy refusal.
     *
     * @since 0.1.0
     */
    public function start(mixed $arguments, RequestContext $context): HostResult;
}
