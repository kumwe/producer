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

final class OperationRegistry
{
    public const CONTRACT_VERSION = HostError::CONTRACT_VERSION;

    /**
     * capability => [route, method, port, portCapability, expectsRevision, mutating, required],
     * sorted by capability exactly as the canonical registry document is.
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

    /** @var array<string, Operation>|null */
    private static ?array $byCapability = null;

    /** @var array<string, Operation>|null */
    private static ?array $byRoute = null;

    private function __construct()
    {
    }

    /**
     * Every operation on the wire, in canonical (capability) order.
     *
     * @return list<Operation>
     */
    public static function all(): array
    {
        return array_values(self::capabilityIndex());
    }

    public static function isCapability(string $capability): bool
    {
        return isset(self::TABLE[$capability]);
    }

    public static function isRoute(string $route): bool
    {
        return isset(self::routeIndex()[$route]);
    }

    /**
     * @throws HostRefusal invalid-request for a capability outside the closed registry
     */
    public static function byCapability(string $capability): Operation
    {
        return self::capabilityIndex()[$capability] ?? throw self::unknownOperation();
    }

    /**
     * @throws HostRefusal invalid-request for a route outside the closed registry
     */
    public static function byRoute(string $route): Operation
    {
        return self::routeIndex()[$route] ?? throw self::unknownOperation();
    }

    /**
     * The canonical host-operations registry document for this pin.
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
     * @return array<string, Operation>
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
     * @return array<string, Operation>
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

    private static function unknownOperation(): HostRefusal
    {
        return new HostRefusal(HostError::invalidRequest(new MessageReference(
            'kumwe.producer/unknown-operation',
            'The requested operation is not in the pinned Studio operation registry.'
        )));
    }
}
