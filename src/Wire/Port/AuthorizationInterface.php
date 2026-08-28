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

interface AuthorizationInterface
{
    public function authorize(Operation $operation, RequestEnvelope $request): ?HostError;
}
