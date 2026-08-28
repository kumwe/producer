<?php

/**
 * One row of the closed host operation registry, host-operations.schema.json.
 *
 * Instances are built only by {@see OperationRegistry} from the pinned
 * contract table; nothing else should construct one, because an Operation
 * that is not in the registry is not on the wire.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire;

final class Operation
{
    public function __construct(
        public readonly string $capability,
        public readonly string $route,
        public readonly string $method,
        public readonly string $port,
        public readonly string $portCapability,
        public readonly bool $expectsRevision,
        public readonly bool $mutating,
        public readonly bool $required,
    ) {
    }

    /**
     * The schema-shaped registry entry.
     */
    public function toDocument(): \stdClass
    {
        $document = new \stdClass();
        $document->capability = $this->capability;
        $document->expectsRevision = $this->expectsRevision;
        $document->method = $this->method;
        $document->mutating = $this->mutating;
        $document->operation = substr($this->route, strpos($this->route, '/') + 1);
        $document->port = $this->port;
        $document->portCapability = $this->portCapability;
        $document->required = $this->required;
        $document->route = $this->route;

        return $document;
    }
}
