<?php

/**
 * The localization port, studio.port/localization.
 *
 * Message catalogues through the host's translation chain. Refusal is a
 * thrown {@see HostRefusal}; an unknown locale is not-found per the
 * pinned host vectors.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\RequestContext;

/**
 * Message catalogues through the host's translation chain, optional.
 *
 * What the host receives: only calls the dispatcher has already
 * validated, cross-checked against the registry row, and had allowed by
 * the host's own {@see AuthorizationInterface}. What the host must
 * guarantee back: a {@see HostResult} carrying the requested message
 * bundle, refusing only by throwing {@see HostRefusal} with a taxonomy
 * category and a non-disclosing message — an unknown locale is not-found,
 * per the pinned host vectors.
 *
 * @since   0.1.0
 */
interface LocalizationPortInterface
{
    /**
     * studio.operation/localization.messages — a message bundle for a
     * locale and namespaces.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The message bundle for the requested locale and
     *                      namespaces.
     *
     * @throws  HostRefusal  A taxonomy refusal — not-found for an unknown
     *                       locale.
     *
     * @since   0.1.0
     */
    public function messages(mixed $arguments, RequestContext $context): HostResult;
}
