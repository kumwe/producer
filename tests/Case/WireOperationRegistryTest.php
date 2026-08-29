<?php

/**
 * The closed operation registry: all twenty-four pinned operations with their
 * exact metadata, refusal of anything outside the registry, and byte
 * equality with the pinned contract's canonical registry document.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Tests\TestCase;
use Kumwe\Producer\Wire\Operation;
use Kumwe\Producer\Wire\OperationRegistry;

final class WireOperationRegistryTest extends TestCase
{
    public function testTheRegistryReproducesThePinnedDocumentByteForByte(): void
    {
        $document = OperationRegistry::document();
        $fixture = CanonicalJson::decode((string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/studio-contract/testkit/fixtures/host-operations.example.json'
        ));
        $this->assertSame('host-operations', $document->kind, 'The registry document kind is fixed.');
        $this->assertSame(OperationRegistry::CONTRACT_VERSION, $document->contractVersion, 'The contract version is pinned.');
        $this->assertSame(
            CanonicalJson::stringify($fixture),
            CanonicalJson::stringify($document),
            'The registry must be canonically identical to the released Studio registry fixture.'
        );
    }

    public function testTheRegistryIsClosedAtTwentyFourOperationsAcrossNinePorts(): void
    {
        $operations = OperationRegistry::all();
        $this->assertSame(24, count($operations), 'The pinned registry binds exactly twenty-four operations.');

        $ports = [];
        $capabilities = [];
        $routes = [];
        $methodsByPort = [];
        foreach ($operations as $operation) {
            $ports[$operation->port] = true;
            $capabilities[] = $operation->capability;
            $routes[] = $operation->route;
            $methodsByPort[$operation->port . '.' . $operation->method] = true;
        }
        $portNames = array_keys($ports);
        sort($portNames, SORT_STRING);
        $this->assertSame(
            ['artifact', 'localization', 'media', 'model', 'permission', 'preview', 'recovery', 'resource', 'telemetry'],
            $portNames,
            'Exactly the nine contract ports appear.'
        );
        $this->assertSame(count($capabilities), count(array_unique($capabilities)), 'Capabilities are unique.');
        $this->assertSame(count($routes), count(array_unique($routes)), 'Routes are unique.');
        $this->assertSame(24, count($methodsByPort), 'Method names are unique within their port.');

        $sorted = $capabilities;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $capabilities, 'The registry lists operations in canonical capability order.');
    }

    public function testConcurrencyMutationAndRequirednessFlagsMatchThePin(): void
    {
        $expectsRevision = [];
        $mutating = [];
        $required = [];
        foreach (OperationRegistry::all() as $operation) {
            if ($operation->expectsRevision) {
                $expectsRevision[] = $operation->capability;
            }
            if ($operation->mutating) {
                $mutating[] = $operation->capability;
            }
            if ($operation->required) {
                $required[] = $operation->capability;
            }
        }
        $this->assertSame(
            [
                'studio.operation/artifact.publish',
                'studio.operation/artifact.save',
                'studio.operation/artifact.unpublish',
            ],
            $expectsRevision,
            'Exactly the three artifact mutations are concurrency-protected.'
        );
        $this->assertSame(
            [
                'studio.operation/artifact.publish',
                'studio.operation/artifact.save',
                'studio.operation/artifact.unpublish',
                'studio.operation/media.abort-upload',
                'studio.operation/media.authorize-upload',
                'studio.operation/media.complete-upload',
                'studio.operation/media.import-external',
                'studio.operation/recovery.discard',
                'studio.operation/recovery.store',
                'studio.operation/telemetry.emit',
            ],
            $mutating,
            'The mutating set matches the pinned registry.'
        );
        $this->assertSame(
            [
                'studio.operation/artifact.dependencies',
                'studio.operation/artifact.load',
                'studio.operation/artifact.publish',
                'studio.operation/artifact.save',
                'studio.operation/artifact.unpublish',
            ],
            $required,
            'Only the artifact port is required.'
        );
    }

    public function testLookupsResolveTheSameOperationByCapabilityAndRoute(): void
    {
        $save = OperationRegistry::byCapability('studio.operation/artifact.save');
        $this->assertSame('artifact/save', $save->route, 'The save route matches the pin.');
        $this->assertSame('save', $save->method, 'The save method matches the pin.');
        $this->assertSame('studio.port/artifact', $save->portCapability, 'The port capability matches the pin.');
        $this->assertSame($save, OperationRegistry::byRoute('artifact/save'), 'Both indexes hold one instance.');

        $abort = OperationRegistry::byRoute('media/abort-upload');
        $this->assertSame('abortUpload', $abort->method, 'Wire spellings map to identifier methods.');
        $this->assertSame('abort-upload', $abort->toDocument()->operation, 'The operation local name derives from the route.');

        $this->assertTrue(OperationRegistry::isCapability('studio.operation/telemetry.emit'), 'Known capability.');
        $this->assertTrue(OperationRegistry::isRoute('telemetry/emit'), 'Known route.');
        $this->assertTrue(!OperationRegistry::isCapability('studio.operation/artifact.delete'), 'Unknown capability.');
        $this->assertTrue(!OperationRegistry::isRoute('artifact/delete'), 'Unknown route.');
    }

    public function testAnythingOutsideTheRegistryIsATypedRefusal(): void
    {
        foreach (['artifact/delete', 'unknown/load', 'artifact', '', 'artifact/load/extra'] as $route) {
            $refusal = $this->assertThrows(
                static fn (): Operation => OperationRegistry::byRoute($route),
                HostRefusal::class,
                "Route {$route} must be refused."
            );
            $this->assertSame(
                'invalid-request',
                $refusal->error()->category(),
                'An unknown route refuses as invalid-request, never a passthrough.'
            );
            $this->assertSame(false, $refusal->error()->retryable(), 'The refusal is not retryable.');
        }
        $refusal = $this->assertThrows(
            static fn (): Operation => OperationRegistry::byCapability('studio.operation/artifact.delete'),
            HostRefusal::class,
            'An unknown capability must be refused.'
        );
        $this->assertSame('invalid-request', $refusal->error()->category(), 'Unknown capability is invalid-request.');
    }
}
