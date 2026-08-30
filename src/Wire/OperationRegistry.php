<?php

/**
 * The closed registry of every host port and operation on the wire.
 *
 * Exactly the thirty-one operations the pinned contract's host-operations
 * registry binds — the capability a host advertises, the transport route a
 * request addresses, the typed method a port interface exposes, and the
 * concurrency/mutation/requiredness flags. The enums in the vendored
 * host-operations.schema.json close the capability, route, port, and
 * port-capability vocabularies; the per-operation metadata reproduces the
 * pinned release's canonical registry document byte for byte (proven by
 * digest in the test suite).
 *
 * The registry is closed: an unknown port or operation is a typed
 * invalid-request refusal, never a passthrough.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire;

use Kumwe\Producer\Error\HostError;
use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Error\MessageReference;

/**
 * The pinned operation registry as a closed lookup: thirty-one operations,
 * ten ports, nothing else.
 *
 * The table below reproduces the pinned release's canonical registry
 * document byte for byte — {@see document()} rebuilds that document and
 * the suite proves its digest — so a lookup here is a lookup in the
 * contract itself. Lookups outside the vocabulary refuse as typed
 * invalid-request; there is no way to route to an operation the pin does
 * not name.
 *
 * @since   0.1.0
 */
final class OperationRegistry
{
    /**
     * The pinned contract version the registry document declares — the
     * same pin the error taxonomy carries.
     *
     * @since   0.1.0
     */
    public const CONTRACT_VERSION = HostError::CONTRACT_VERSION;

    /**
     * capability => [route, method, port, portCapability, expectsRevision, mutating, required],
     * sorted by capability exactly as the canonical registry document is.
     *
     * @since   0.1.0
     */
    private const TABLE = [
        'studio.operation/artifact.dependencies' => ['artifact/dependencies', 'dependencies', 'artifact', 'studio.port/artifact', false, false, true],
        'studio.operation/artifact.load' => ['artifact/load', 'load', 'artifact', 'studio.port/artifact', false, false, true],
        'studio.operation/artifact.publish' => ['artifact/publish', 'publish', 'artifact', 'studio.port/artifact', true, true, true],
        'studio.operation/artifact.save' => ['artifact/save', 'save', 'artifact', 'studio.port/artifact', true, true, true],
        'studio.operation/artifact.unpublish' => ['artifact/unpublish', 'unpublish', 'artifact', 'studio.port/artifact', true, true, true],
        'studio.operation/authoring.list-types' => ['authoring/list-types', 'listTypes', 'authoring', 'studio.port/authoring', false, false, false],
        'studio.operation/authoring.plan-save' => ['authoring/plan-save', 'planSave', 'authoring', 'studio.port/authoring', false, false, false],
        'studio.operation/authoring.resolve-target' => ['authoring/resolve-target', 'resolveTarget', 'authoring', 'studio.port/authoring', false, false, false],
        'studio.operation/authoring.save-as-new-type' => ['authoring/save-as-new-type', 'saveAsNewType', 'authoring', 'studio.port/authoring', false, true, false],
        'studio.operation/authoring.save-item' => ['authoring/save-item', 'saveItem', 'authoring', 'studio.port/authoring', false, true, false],
        'studio.operation/authoring.save-new-type-version' => ['authoring/save-new-type-version', 'saveNewTypeVersion', 'authoring', 'studio.port/authoring', false, true, false],
        'studio.operation/authoring.start' => ['authoring/start', 'start', 'authoring', 'studio.port/authoring', false, true, false],
        'studio.operation/localization.messages' => ['localization/messages', 'messages', 'localization', 'studio.port/localization', false, false, false],
        'studio.operation/media.abort-upload' => ['media/abort-upload', 'abortUpload', 'media', 'studio.port/media', false, true, false],
        'studio.operation/media.authorize-upload' => ['media/authorize-upload', 'authorizeUpload', 'media', 'studio.port/media', false, true, false],
        'studio.operation/media.complete-upload' => ['media/complete-upload', 'completeUpload', 'media', 'studio.port/media', false, true, false],
        'studio.operation/media.get' => ['media/get', 'get', 'media', 'studio.port/media', false, false, false],
        'studio.operation/media.import-external' => ['media/import-external', 'importExternal', 'media', 'studio.port/media', false, true, false],
        'studio.operation/media.list' => ['media/list', 'list', 'media', 'studio.port/media', false, false, false],
        'studio.operation/media.upload-status' => ['media/upload-status', 'uploadStatus', 'media', 'studio.port/media', false, false, false],
        'studio.operation/model.get' => ['model/get', 'get', 'model', 'studio.port/model', false, false, false],
        'studio.operation/model.list' => ['model/list', 'list', 'model', 'studio.port/model', false, false, false],
        'studio.operation/permission.explain' => ['permission/explain', 'explain', 'permission', 'studio.port/permission', false, false, false],
        'studio.operation/permission.refresh' => ['permission/refresh', 'refresh', 'permission', 'studio.port/permission', false, false, false],
        'studio.operation/preview.cancel' => ['preview/cancel', 'cancel', 'preview', 'studio.port/preview', false, false, false],
        'studio.operation/preview.render' => ['preview/render', 'render', 'preview', 'studio.port/preview', false, false, false],
        'studio.operation/recovery.discard' => ['recovery/discard', 'discard', 'recovery', 'studio.port/recovery', false, true, false],
        'studio.operation/recovery.load' => ['recovery/load', 'load', 'recovery', 'studio.port/recovery', false, false, false],
        'studio.operation/recovery.store' => ['recovery/store', 'store', 'recovery', 'studio.port/recovery', false, true, false],
        'studio.operation/resource.search' => ['resource/search', 'search', 'resource', 'studio.port/resource', false, false, false],
        'studio.operation/telemetry.emit' => ['telemetry/emit', 'emit', 'telemetry', 'studio.port/telemetry', false, true, false],
    ];

    /**
     * The capability-keyed index, materialized from the table on first
     * use; deterministic, so caching is observationally pure.
     *
     * @var     array<string, Operation>|null
     *
     * @since   0.1.0
     */
    private static ?array $byCapability = null;

    /**
     * The route-keyed index, materialized from the capability index on
     * first use.
     *
     * @var     array<string, Operation>|null
     *
     * @since   0.1.0
     */
    private static ?array $byRoute = null;

    /**
     * Not constructable: the registry is the pinned static table.
     *
     * @since   0.1.0
     */
    private function __construct()
    {
    }

    /**
     * Every operation on the wire, in canonical (capability) order.
     *
     * @return  list<Operation>  The thirty-one pinned operations.
     *
     * @since   0.1.0
     */
    public static function all(): array
    {
        return array_values(self::capabilityIndex());
    }

    /**
     * Whether a capability name is in the closed registry.
     *
     * @param   string  $capability  The qualified operation name to test.
     *
     * @return  bool  True only for one of the thirty-one pinned
     *                capabilities.
     *
     * @since   0.1.0
     */
    public static function isCapability(string $capability): bool
    {
        return isset(self::TABLE[$capability]);
    }

    /**
     * Whether a transport route is in the closed registry.
     *
     * @param   string  $route  The route to test, e.g. `artifact/save`.
     *
     * @return  bool  True only for one of the thirty-one pinned routes.
     *
     * @since   0.1.0
     */
    public static function isRoute(string $route): bool
    {
        return isset(self::routeIndex()[$route]);
    }

    /**
     * The registry row for a capability name.
     *
     * @param   string  $capability  The qualified operation name.
     *
     * @return  Operation  The pinned row for that capability.
     *
     * @throws  HostRefusal  invalid-request for a capability outside the closed registry
     *
     * @since   0.1.0
     */
    public static function byCapability(string $capability): Operation
    {
        return self::capabilityIndex()[$capability] ?? throw self::unknownOperation();
    }

    /**
     * The registry row for a transport route.
     *
     * @param   string  $route  The route a request addresses.
     *
     * @return  Operation  The pinned row for that route.
     *
     * @throws  HostRefusal  invalid-request for a route outside the closed registry
     *
     * @since   0.1.0
     */
    public static function byRoute(string $route): Operation
    {
        return self::routeIndex()[$route] ?? throw self::unknownOperation();
    }

    /**
     * The canonical host-operations registry document for this pin.
     *
     * @return  \stdClass  The document whose canonical bytes are
     *                     byte-identical to the pinned release's registry
     *                     document, as the suite proves by digest.
     *
     * @since   0.1.0
     */
    public static function document(): \stdClass
    {
        $document = new \stdClass();
        $document->contractVersion = self::CONTRACT_VERSION;
        $document->kind = 'host-operations';
        $document->operations = array_map(
            static fn (Operation $operation): \stdClass => $operation->toDocument(),
            self::all()
        );

        return $document;
    }

    /**
     * Materializes and caches the capability-keyed index from the pinned
     * table, preserving its canonical order.
     *
     * @return  array<string, Operation>  Rows keyed by capability.
     *
     * @since   0.1.0
     */
    private static function capabilityIndex(): array
    {
        if (self::$byCapability === null) {
            $index = [];
            foreach (self::TABLE as $capability => [$route, $method, $port, $portCapability, $expectsRevision, $mutating, $required]) {
                $index[$capability] = new Operation(
                    $capability,
                    $route,
                    $method,
                    $port,
                    $portCapability,
                    $expectsRevision,
                    $mutating,
                    $required
                );
            }
            self::$byCapability = $index;
        }

        return self::$byCapability;
    }

    /**
     * Materializes and caches the route-keyed index; routes are unique in
     * the pinned table, so the mapping is one-to-one.
     *
     * @return  array<string, Operation>  Rows keyed by transport route.
     *
     * @since   0.1.0
     */
    private static function routeIndex(): array
    {
        if (self::$byRoute === null) {
            $index = [];
            foreach (self::capabilityIndex() as $operation) {
                $index[$operation->route] = $operation;
            }
            self::$byRoute = $index;
        }

        return self::$byRoute;
    }

    /**
     * The typed refusal for any lookup outside the closed vocabulary: a
     * fixed invalid-request that names no candidate and echoes nothing.
     *
     * @return  HostRefusal  The refusal ready to throw.
     *
     * @since   0.1.0
     */
    private static function unknownOperation(): HostRefusal
    {
        return new HostRefusal(HostError::invalidRequest(new MessageReference(
            'kumwe.producer/unknown-operation',
            'The requested operation is not in the pinned Studio operation registry.'
        )));
    }
}
