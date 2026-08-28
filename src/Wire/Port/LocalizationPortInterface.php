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

interface LocalizationPortInterface
{
    /**
     * studio.operation/localization.messages — a message bundle for a
     * locale and namespaces.
     *
     * @throws HostRefusal
     */
    public function messages(mixed $arguments, RequestContext $context): HostResult;
}
