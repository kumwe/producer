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

/**
 * One operation as the pinned registry binds it: the capability a host
 * advertises, the transport route a request addresses, the typed port
 * method that serves it, and the flags the dispatcher enforces.
 *
 * The flags are load-bearing contract: expectsRevision marks the
 * concurrency-protected operations whose envelopes must carry
 * expectedRevision and whose results must return the advanced revision;
 * mutating marks the operations that may carry an idempotency key;
 * required marks the operations every conforming host must serve.
 * Construct instances only through {@see OperationRegistry} — an Operation
 * outside the registry is not on the wire.
 *
 * @since   0.1.0
 */
final class Operation
{
    /**
     * Binds one registry row verbatim; no validation happens here because
     * the pinned table in {@see OperationRegistry} is the proven source.
     *
     * @param   string  $capability       The operation's qualified name, e.g.
     *                                    `studio.operation/artifact.save`.
     * @param   string  $route            The transport route, e.g. `artifact/save`.
     * @param   string  $method           The PHP method name on the port interface.
     * @param   string  $port             The port's short name, e.g. `artifact`.
     * @param   string  $portCapability   The port's qualified name, e.g.
     *                                    `studio.port/artifact`.
     * @param   bool    $expectsRevision  Whether the operation is
     *                                    concurrency-protected.
     * @param   bool    $mutating         Whether the operation mutates and may
     *                                    carry an idempotency key.
     * @param   bool    $required         Whether every conforming host must
     *                                    serve the operation.
     *
     * @since   0.1.0
     */
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
     *
     * @return  \stdClass  The entry exactly as the canonical registry
     *                     document carries it.
     *
     * @since   0.1.0
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
