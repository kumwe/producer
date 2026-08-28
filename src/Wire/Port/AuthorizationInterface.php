<?php

/**
 * The host's authorization decision, consulted first for every operation.
 *
 * Producer never decides who may do what: this interface hands the host
 * the resolved operation and the full validated request (the argument is
 * part of the decision — authorization is item-scoped), and the host
 * answers per call. Null allows exactly this call; a HostError refuses it
 * and is emitted verbatim as the canonical refusal — typically
 * unauthenticated or forbidden, but any category the host's policy
 * requires (a stale session generation is its invalid-request, a rate
 * window its rate-limited). Producer never caches an allow, and a
 * refusal stops the dispatch before any later stage runs.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Error\HostError;
use Kumwe\Producer\Wire\Operation;
use Kumwe\Producer\Wire\RequestEnvelope;

/**
 * The per-call authorization authority every dispatch consults first.
 *
 * What the host receives: the resolved registry row and the fully
 * validated envelope, argument included, because authorization is
 * item-scoped. What the host must guarantee: an answer for every call —
 * null to allow exactly this call, or a HostError to refuse it, emitted
 * verbatim as the canonical refusal. An allow is never cached; a refusal
 * stops the dispatch before replay, before the port. Anything thrown here
 * other than a HostRefusal fails closed as a non-disclosing internal
 * refusal, because no decision means no.
 *
 * @since   0.1.0
 */
interface AuthorizationInterface
{
    /**
     * Decides this one call. Called before every operation, replays
     * included; the answer applies to exactly this request and is never
     * cached.
     *
     * @param   Operation        $operation  The resolved registry row being
     *                                       attempted.
     * @param   RequestEnvelope  $request    The validated request, argument
     *                                       included.
     *
     * @return  HostError|null  Null to allow; a taxonomy refusal —
     *                          typically unauthenticated or forbidden, but
     *                          any category the host's policy requires —
     *                          to refuse, emitted verbatim.
     *
     * @since   0.1.0
     */
    public function authorize(Operation $operation, RequestEnvelope $request): ?HostError;
}
