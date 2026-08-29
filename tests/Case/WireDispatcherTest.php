<?php

/**
 * Dispatch through a tiny in-memory fake host: authorization first and
 * fail-closed, idempotent replay with the vectors' changed-intent policy,
 * verbatim host refusals, non-disclosing internal mapping, and result
 * discipline for concurrency-protected operations. The fakes live here in
 * the test suite — src never contains storage or a host.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Error\HostError;
use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Error\MessageReference;
use Kumwe\Producer\Tests\TestCase;
use Kumwe\Producer\Wire\Dispatcher;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\MutationOutcome;
use Kumwe\Producer\Wire\Operation;
use Kumwe\Producer\Wire\Port\ArtifactPortInterface;
use Kumwe\Producer\Wire\Port\AuthorizationInterface;
use Kumwe\Producer\Wire\Port\HostAdapterInterface;
use Kumwe\Producer\Wire\Port\LocalizationPortInterface;
use Kumwe\Producer\Wire\Port\MediaPortInterface;
use Kumwe\Producer\Wire\Port\ModelPortInterface;
use Kumwe\Producer\Wire\Port\MutationBoundaryInterface;
use Kumwe\Producer\Wire\Port\PermissionPortInterface;
use Kumwe\Producer\Wire\Port\PreviewPortInterface;
use Kumwe\Producer\Wire\Port\RecoveryPortInterface;
use Kumwe\Producer\Wire\Port\ResourcePortInterface;
use Kumwe\Producer\Wire\Port\TelemetryPortInterface;
use Kumwe\Producer\Wire\RequestContext;
use Kumwe\Producer\Wire\RequestEnvelope;
use Kumwe\Producer\Wire\Response;

final class FakeAuthorization implements AuthorizationInterface
{
    /** @var list<string> */
    public array $decisions = [];

    public ?HostError $refusal = null;

    public ?\Throwable $failure = null;

    public function authorize(Operation $operation, RequestEnvelope $request): ?HostError
    {
        $this->decisions[] = $operation->capability;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->refusal;
    }
}

final class FakeMutationBoundary implements MutationBoundaryInterface
{
    /** @var array<string, MutationOutcome> */
    public array $records = [];

    public int $recalls = 0;

    public int $recordings = 0;

    /** @var list<string> */
    public array $events = [];

    /** @var list<array{string|null, string|null}> */
    public array $coordinates = [];

    /** @var (callable(HostResult|HostError): (HostResult|HostError))|null */
    public $store = null;

    /** @var (callable(HostResult|HostError): (HostResult|HostError))|null */
    public $restore = null;

    public ?\Throwable $failure = null;

    public function execute(
        Operation $operation,
        RequestEnvelope $request,
        ?string $scopeKey,
        ?string $intentDigest,
        callable $mutation,
    ): MutationOutcome
    {
        $this->recalls++;
        $this->events[] = 'begin';
        $this->coordinates[] = [$scopeKey, $intentDigest];
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($scopeKey !== null && isset($this->records[$scopeKey])) {
            $this->events[] = 'replay';

            $record = $this->records[$scopeKey];
            $outcome = $record->outcome();
            if (is_callable($this->restore)) {
                $outcome = ($this->restore)($outcome);
            }

            return new MutationOutcome($record->intentDigest, $outcome);
        }

        $this->events[] = 'mutation';
        $outcome = $mutation();
        $stored = is_callable($this->store) ? ($this->store)($outcome) : $outcome;
        $record = new MutationOutcome($intentDigest, $stored);
        if ($scopeKey !== null) {
            $this->recordings++;
            $this->records[$scopeKey] = $record;
        }
        $this->events[] = 'commit';

        return new MutationOutcome($intentDigest, $outcome);
    }
}

final class FakeArtifactPort implements ArtifactPortInterface
{
    /** @var list<array{string, mixed, RequestContext}> */
    public array $calls = [];

    /** @var array<string, callable(mixed, RequestContext): HostResult> */
    public array $behaviours = [];

    private function call(string $method, mixed $arguments, RequestContext $context): HostResult
    {
        $this->calls[] = [$method, $arguments, $context];
        $behaviour = $this->behaviours[$method] ?? static fn (): HostResult => new HostResult(null);

        return $behaviour($arguments, $context);
    }

    public function dependencies(mixed $arguments, RequestContext $context): HostResult
    {
        return $this->call('dependencies', $arguments, $context);
    }

    public function load(mixed $arguments, RequestContext $context): HostResult
    {
        return $this->call('load', $arguments, $context);
    }

    public function publish(mixed $arguments, RequestContext $context): HostResult
    {
        return $this->call('publish', $arguments, $context);
    }

    public function save(mixed $arguments, RequestContext $context): HostResult
    {
        return $this->call('save', $arguments, $context);
    }

    public function unpublish(mixed $arguments, RequestContext $context): HostResult
    {
        return $this->call('unpublish', $arguments, $context);
    }
}

final class FakeHost implements HostAdapterInterface
{
    public FakeAuthorization $authorizationFake;

    public FakeMutationBoundary $mutations;

    public FakeArtifactPort $artifactFake;

    public function __construct()
    {
        $this->authorizationFake = new FakeAuthorization();
        $this->mutations = new FakeMutationBoundary();
        $this->artifactFake = new FakeArtifactPort();
    }

    public function authorization(): AuthorizationInterface
    {
        return $this->authorizationFake;
    }

    public function mutations(): MutationBoundaryInterface
    {
        return $this->mutations;
    }

    public function artifact(): ArtifactPortInterface
    {
        return $this->artifactFake;
    }

    public function localization(): ?LocalizationPortInterface
    {
        return null;
    }

    public function media(): ?MediaPortInterface
    {
        return null;
    }

    public function model(): ?ModelPortInterface
    {
        return null;
    }

    public function permission(): ?PermissionPortInterface
    {
        return null;
    }

    public function preview(): ?PreviewPortInterface
    {
        return null;
    }

    public function recovery(): ?RecoveryPortInterface
    {
        return null;
    }

    public function resource(): ?ResourcePortInterface
    {
        return null;
    }

    public function telemetry(): ?TelemetryPortInterface
    {
        return null;
    }
}

final class WireDispatcherTest extends TestCase
{
    /**
     * @param array<string, mixed> $contextOverrides
     * @param array<string, mixed>|null $arguments
     */
    private static function body(
        string $operationId,
        array $contextOverrides = [],
        ?array $arguments = ['id' => 'vector.blueprint', 'version' => '1.0.0'],
    ): string {
        $document = [];
        if ($arguments !== null) {
            $document['arguments'] = $arguments;
        }
        $context = array_merge([
            'operationId' => $operationId,
            'protocolVersion' => RequestEnvelope::WIRE_PROTOCOL_VERSION,
            'requestId' => 'requests/test-1',
            'resourceContextKey' => 'contexts/test',
            'sessionGeneration' => 'session-r1',
        ], $contextOverrides);
        $document['context'] = $context;

        return json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function assertRefusalCategory(Response $response, string $category, string $message): void
    {
        $this->assertSame(true, $response->refusal, $message . ' (flag)');
        $document = CanonicalJson::decode($response->body);
        $this->assertSame($category, $document->category, $message . ' (category)');
        $this->assertSame('host-error', $document->kind, $message . ' (kind)');
    }

    public function testAnUnknownRouteIsRefusedWithoutTouchingTheHost(): void
    {
        $host = new FakeHost();
        $response = (new Dispatcher($host))->dispatch(
            'artifact/delete',
            self::body('studio.operation/artifact.load')
        );
        $this->assertRefusalCategory($response, 'invalid-request', 'An unknown route is a typed refusal.');
        $this->assertSame([], $host->authorizationFake->decisions, 'No decision is asked for an unknown route.');
        $this->assertSame([], $host->artifactFake->calls, 'No port is touched for an unknown route.');
        $this->assertSame(0, $host->mutations->recalls, 'The mutation boundary is not consulted for an unknown route.');
    }

    public function testAMalformedBodyIsRefusedBeforeAuthorization(): void
    {
        $host = new FakeHost();
        $response = (new Dispatcher($host))->dispatch('artifact/load', '{"context":');
        $this->assertRefusalCategory($response, 'invalid-request', 'A malformed body is refused.');
        $this->assertSame([], $host->authorizationFake->decisions, 'Validation precedes authorization.');
    }

    public function testTheBodyBoundIsEnforcedThroughTheDispatcher(): void
    {
        $host = new FakeHost();
        $body = self::body('studio.operation/artifact.load');
        $response = (new Dispatcher($host, maximumBodyBytes: strlen($body) - 1))->dispatch('artifact/load', $body);
        $this->assertRefusalCategory($response, 'limit-exceeded', 'The configured bound refuses oversize bodies.');
        $this->assertSame([], $host->artifactFake->calls, 'No port is touched past the bound.');
    }

    public function testAnEnvelopeNamingAnotherOperationIsRefused(): void
    {
        $host = new FakeHost();
        $response = (new Dispatcher($host))->dispatch(
            'artifact/load',
            self::body('studio.operation/artifact.dependencies')
        );
        $this->assertRefusalCategory($response, 'invalid-request', 'Route and envelope must agree.');
        $this->assertStringContains('kumwe.producer/operation-mismatch', $response->body, 'The refusal names its rule.');
        $this->assertSame([], $host->artifactFake->calls, 'No port is touched on a mismatch.');
    }

    public function testRevisionAndIdempotencyKeyApplicabilityFollowTheRegistry(): void
    {
        $host = new FakeHost();
        $dispatcher = new Dispatcher($host);

        $response = $dispatcher->dispatch(
            'artifact/save',
            self::body('studio.operation/artifact.save', ['idempotencyKey' => 'idempotency/save-1'])
        );
        $this->assertRefusalCategory($response, 'invalid-request', 'A concurrency-protected save needs expectedRevision.');
        $this->assertStringContains('kumwe.producer/expected-revision-required', $response->body, 'Named rule.');

        $response = $dispatcher->dispatch(
            'artifact/load',
            self::body('studio.operation/artifact.load', ['expectedRevision' => 'vector.blueprint-r1'])
        );
        $this->assertRefusalCategory($response, 'invalid-request', 'Only protected operations carry expectedRevision.');
        $this->assertStringContains('kumwe.producer/expected-revision-unexpected', $response->body, 'Named rule.');

        $response = $dispatcher->dispatch(
            'artifact/load',
            self::body('studio.operation/artifact.load', ['idempotencyKey' => 'idempotency/load-1'])
        );
        $this->assertRefusalCategory($response, 'invalid-request', 'Only mutations carry an idempotency key.');
        $this->assertStringContains('kumwe.producer/idempotency-key-unexpected', $response->body, 'Named rule.');

        $this->assertSame([], $host->artifactFake->calls, 'No port is touched by any applicability refusal.');
        $this->assertSame([], $host->authorizationFake->decisions, 'Envelope checks precede authorization.');
    }

    public function testAnAuthorizationRefusalStopsDispatchBeforeAnyLaterStage(): void
    {
        $host = new FakeHost();
        $host->authorizationFake->refusal = HostError::forbidden(
            new MessageReference('studio.host/forbidden', 'This operation is not allowed.')
        );
        $response = (new Dispatcher($host))->dispatch(
            'artifact/save',
            self::body('studio.operation/artifact.save', [
                'expectedRevision' => 'vector.blueprint-r1',
                'idempotencyKey' => 'idempotency/save-1',
            ])
        );
        $this->assertRefusalCategory($response, 'forbidden', 'The host refusal is emitted verbatim.');
        $this->assertStringContains('studio.host/forbidden', $response->body, 'The host message key survives.');
        $this->assertSame(
            ['studio.operation/artifact.save'],
            $host->authorizationFake->decisions,
            'The host decided exactly this operation.'
        );
        $this->assertSame([], $host->artifactFake->calls, 'The port is never touched after a refusal.');
        $this->assertSame(0, $host->mutations->recalls, 'The mutation boundary is never consulted after a refusal.');
        $this->assertSame(0, $host->mutations->recordings, 'Nothing is recorded after a refusal.');
    }

    public function testAnAuthorizationFailureFailsClosedWithoutDisclosure(): void
    {
        $host = new FakeHost();
        $host->authorizationFake->failure = new \RuntimeException('secret-internal-detail');
        $response = (new Dispatcher($host))->dispatch(
            'artifact/load',
            self::body('studio.operation/artifact.load')
        );
        $this->assertRefusalCategory($response, 'internal', 'No decision means refusal.');
        $this->assertStringExcludes('secret-internal-detail', $response->body, 'Host internals never reach the wire.');
        $this->assertSame([], $host->artifactFake->calls, 'The port is never touched without a decision.');
    }

    public function testTheHappyPathReturnsCanonicalResultBytes(): void
    {
        $host = new FakeHost();
        $host->artifactFake->behaviours['load'] = static fn (): HostResult => new HostResult(
            CanonicalJson::decode('{"kind":"blueprint","id":"vector.blueprint","revision":"vector.blueprint-r1"}')
        );
        $response = (new Dispatcher($host))->dispatch(
            'artifact/load',
            self::body('studio.operation/artifact.load')
        );
        $this->assertSame(false, $response->refusal, 'The load succeeds.');
        $this->assertSame(
            '{"value":{"id":"vector.blueprint","kind":"blueprint","revision":"vector.blueprint-r1"}}',
            $response->body,
            'The result document is canonical.'
        );
        $this->assertSame('application/json', $response->headers['content-type'], 'Content-type discipline holds.');
        $this->assertSame(1, count($host->artifactFake->calls), 'The port ran once.');
        [$method, $arguments, $context] = $host->artifactFake->calls[0];
        $this->assertSame('load', $method, 'The registry method name is used.');
        $this->assertSame('vector.blueprint', $arguments->id, 'The decoded argument reaches the port.');
        $this->assertSame('requests/test-1', $context->requestId, 'The validated context reaches the port.');
        $this->assertSame(0, $host->mutations->recalls, 'A read never consults the mutation boundary.');
    }

    public function testAnUnkeyedMutationKeepsTheHostAtomicBoundary(): void
    {
        $host = new FakeHost();
        $host->artifactFake->behaviours['publish'] = static fn (): HostResult => new HostResult(
            null,
            'vector.blueprint-r2',
        );
        $response = (new Dispatcher($host))->dispatch('artifact/publish', self::body(
            'studio.operation/artifact.publish',
            ['expectedRevision' => 'vector.blueprint-r1'],
        ));

        $this->assertSame(false, $response->refusal, 'The unkeyed publish succeeds.');
        $this->assertSame(
            ['begin', 'mutation', 'commit'],
            $host->mutations->events,
            'Transaction and audit ownership do not depend on an idempotency key.',
        );
        $this->assertSame(
            [[null, null]],
            $host->mutations->coordinates,
            'An unkeyed mutation has no invented replay coordinates.',
        );
        $this->assertSame(0, $host->mutations->recordings, 'An unkeyed mutation creates no replay entry.');
    }

    public function testAKeyedMutationRecordsThenReplaysWithoutReapplying(): void
    {
        $host = new FakeHost();
        $host->artifactFake->behaviours['save'] = static fn (): HostResult => new HostResult(null, 'vector.blueprint-r2');
        $dispatcher = new Dispatcher($host);
        $body = self::body('studio.operation/artifact.save', [
            'expectedRevision' => 'vector.blueprint-r1',
            'idempotencyKey' => 'idempotency/save-1',
        ]);

        $first = $dispatcher->dispatch('artifact/save', $body);
        $this->assertSame(false, $first->refusal, 'The save is accepted.');
        $this->assertSame(
            '{"revision":"vector.blueprint-r2","value":null}',
            $first->body,
            'A protected mutation returns the advanced revision.'
        );
        $this->assertSame(1, count($host->artifactFake->calls), 'The mutation ran once.');
        $this->assertSame(1, $host->mutations->recordings, 'The accepted outcome is recorded.');
        $this->assertSame(
            ['begin', 'mutation', 'commit'],
            $host->mutations->events,
            'The host atomic boundary surrounds mutation and replay persistence.'
        );

        $replay = $dispatcher->dispatch('artifact/save', $body);
        $this->assertSame($first->body, $replay->body, 'The replay returns the original outcome.');
        $this->assertSame(1, count($host->artifactFake->calls), 'The mutation is not applied twice.');
        $this->assertSame(1, $host->mutations->recordings, 'A replay records nothing new.');
        $this->assertSame(
            ['begin', 'mutation', 'commit', 'begin', 'replay'],
            $host->mutations->events,
            'A replay returns from the same boundary without entering the mutation callback.'
        );

        $requestIdChanged = $dispatcher->dispatch('artifact/save', self::body('studio.operation/artifact.save', [
            'expectedRevision' => 'vector.blueprint-r1',
            'idempotencyKey' => 'idempotency/save-1',
            'requestId' => 'requests/test-2',
        ]));
        $this->assertSame($first->body, $requestIdChanged->body, 'Per-attempt request correlation is excluded from intent.');
        $this->assertSame(1, count($host->artifactFake->calls), 'Still not applied twice.');

        $otherScope = $dispatcher->dispatch('artifact/save', self::body('studio.operation/artifact.save', [
            'expectedRevision' => 'vector.blueprint-r1',
            'idempotencyKey' => 'idempotency/save-1',
            'resourceContextKey' => 'contexts/other',
        ]));
        $this->assertSame(false, $otherScope->refusal, 'The same key in another resource context is a new scope.');
        $this->assertSame(2, count($host->artifactFake->calls), 'The other scope applies its own mutation.');
        $this->assertSame(2, $host->mutations->recordings, 'The other scope records its own outcome.');
    }

    public function testAKeyReusedWithChangedIntentIsRefused(): void
    {
        $host = new FakeHost();
        $host->artifactFake->behaviours['save'] = static fn (): HostResult => new HostResult(null, 'vector.blueprint-r2');
        $dispatcher = new Dispatcher($host);
        $context = ['expectedRevision' => 'vector.blueprint-r1', 'idempotencyKey' => 'idempotency/save-1'];

        $first = $dispatcher->dispatch('artifact/save', self::body('studio.operation/artifact.save', $context));
        $this->assertSame(false, $first->refusal, 'The save is accepted.');

        $changedArgument = $dispatcher->dispatch('artifact/save', self::body(
            'studio.operation/artifact.save',
            $context,
            ['id' => 'vector.blueprint', 'version' => '2.0.0']
        ));
        $this->assertRefusalCategory($changedArgument, 'invalid-request', 'A changed argument under the same key is refused.');
        $this->assertStringContains('kumwe.producer/idempotent-intent-changed', $changedArgument->body, 'Named rule.');

        $changedLocale = $dispatcher->dispatch('artifact/save', self::body(
            'studio.operation/artifact.save',
            $context + ['locale' => 'en']
        ));
        $this->assertRefusalCategory($changedLocale, 'invalid-request', 'A changed semantic context field changes intent.');
        $this->assertSame(1, count($host->artifactFake->calls), 'Neither changed retry reached the port.');
    }

    public function testACommittedRefusalCommitsAndReplaysWithoutReapplying(): void
    {
        $host = new FakeHost();
        $host->artifactFake->behaviours['save'] = static function (): HostResult {
            throw new HostRefusal(
                HostError::validationFailed(new MessageReference(
                    'studio.media/upload-verification-failed',
                    'The uploaded bytes failed verification.',
                )),
                commitsState: true,
            );
        };
        $dispatcher = new Dispatcher($host);
        $body = self::body('studio.operation/artifact.save', [
            'expectedRevision' => 'vector.blueprint-r1',
            'idempotencyKey' => 'idempotency/failed-save-1',
        ]);

        $first = $dispatcher->dispatch('artifact/save', $body);
        $this->assertRefusalCategory($first, 'validation-failed', 'The committed failure is delivered.');
        $this->assertSame('validation-failed', $first->refusalCategory, 'Transport sees the category directly.');
        $this->assertSame(1, $host->mutations->recordings, 'The safe failure outcome is committed for replay.');
        $replay = $dispatcher->dispatch('artifact/save', $body);
        $this->assertSame($first->body, $replay->body, 'The committed refusal replays exactly.');
        $this->assertSame(1, count($host->artifactFake->calls), 'Replay does not repeat the failed lifecycle mutation.');
    }

    public function testTheHostCanStoreAProtectedProjectionAndRehydrateReplay(): void
    {
        $host = new FakeHost();
        $grant = (object) [
            'grantId' => 'grants/1',
            'headers' => (object) ['X-Studio-Upload-Token' => 'derived-token'],
        ];
        $host->artifactFake->behaviours['save'] = static fn (): HostResult => new HostResult(
            $grant,
            'vector.blueprint-r2',
        );
        $host->mutations->store = static function (HostResult|HostError $outcome): HostResult|HostError {
            if (!$outcome instanceof HostResult || !$outcome->value instanceof \stdClass) {
                return $outcome;
            }
            $stored = clone $outcome->value;
            $stored->headers = clone $stored->headers;
            unset($stored->headers->{'X-Studio-Upload-Token'});

            return new HostResult($stored, $outcome->revision);
        };
        $host->mutations->restore = static function (HostResult|HostError $outcome): HostResult|HostError {
            if (!$outcome instanceof HostResult || !$outcome->value instanceof \stdClass) {
                return $outcome;
            }
            $restored = clone $outcome->value;
            $restored->headers = clone $restored->headers;
            $restored->headers->{'X-Studio-Upload-Token'} = 'derived-token';

            return new HostResult($restored, $outcome->revision);
        };
        $dispatcher = new Dispatcher($host);
        $body = self::body('studio.operation/artifact.save', [
            'expectedRevision' => 'vector.blueprint-r1',
            'idempotencyKey' => 'idempotency/protected-1',
        ]);

        $first = $dispatcher->dispatch('artifact/save', $body);
        $stored = array_values($host->mutations->records)[0]->outcome();
        $this->assertTrue($stored instanceof HostResult, 'The host stored its protected logical projection.');
        $this->assertStringExcludes(
            'derived-token',
            CanonicalJson::stringify($stored->toDocument()),
            'The live capability is absent from idempotency storage.',
        );
        $replay = $dispatcher->dispatch('artifact/save', $body);
        $this->assertSame($first->body, $replay->body, 'Verified deterministic rehydration restores exact wire behavior.');
        $this->assertSame(1, count($host->artifactFake->calls), 'Rehydration never repeats the mutation.');
    }

    public function testAnAtomicExecutionFailureRefusesBeforeTheMutationRuns(): void
    {
        $host = new FakeHost();
        $host->mutations->failure = new \RuntimeException('database-transaction-secret');
        $response = (new Dispatcher($host))->dispatch('artifact/save', self::body(
            'studio.operation/artifact.save',
            [
                'expectedRevision' => 'vector.blueprint-r1',
                'idempotencyKey' => 'idempotency/save-1',
            ]
        ));

        $this->assertRefusalCategory($response, 'internal', 'An unavailable atomic boundary fails closed.');
        $this->assertStringContains(
            'kumwe.producer/mutation-transaction-failed',
            $response->body,
            'The refusal names the atomic boundary.'
        );
        $this->assertStringExcludes('database-transaction-secret', $response->body, 'Storage details stay private.');
        $this->assertSame([], $host->artifactFake->calls, 'The mutation cannot run outside the atomic boundary.');
        $this->assertSame(0, $host->mutations->recordings, 'No replay record is left behind.');
    }

    public function testAHostConflictPassesThroughVerbatimAndIsNotRecorded(): void
    {
        $host = new FakeHost();
        $host->artifactFake->behaviours['save'] = static function (): HostResult {
            throw new HostRefusal(HostError::conflict(
                new MessageReference('studio.host/save-conflict', 'Another revision was accepted while you were editing.'),
                'vector.blueprint-r3'
            ));
        };
        $response = (new Dispatcher($host))->dispatch('artifact/save', self::body('studio.operation/artifact.save', [
            'expectedRevision' => 'vector.blueprint-r1',
            'idempotencyKey' => 'idempotency/save-1',
        ]));
        $this->assertRefusalCategory($response, 'conflict', 'The host conflict is emitted verbatim.');
        $document = CanonicalJson::decode($response->body);
        $this->assertSame('vector.blueprint-r3', $document->revision, 'The safe current revision is on the wire.');
        $this->assertSame(false, $document->retryable, 'A conflict is not retryable as-is.');
        $this->assertSame(0, $host->mutations->recordings, 'Refused mutations are never recorded for replay.');
    }

    public function testAPortExceptionBecomesANonDisclosingInternalRefusal(): void
    {
        $host = new FakeHost();
        $host->artifactFake->behaviours['load'] = static function (): HostResult {
            throw new \RuntimeException('database password is hunter2');
        };
        $response = (new Dispatcher($host))->dispatch('artifact/load', self::body('studio.operation/artifact.load'));
        $this->assertRefusalCategory($response, 'internal', 'A host implementation fault fails closed.');
        $this->assertStringExcludes('hunter2', $response->body, 'Exception text never reaches the wire.');
        $this->assertStringExcludes('database', $response->body, 'Exception text never reaches the wire at all.');
    }

    public function testAProtectedMutationWithoutAnAdvancedRevisionFailsClosed(): void
    {
        $host = new FakeHost();
        $host->artifactFake->behaviours['save'] = static fn (): HostResult => new HostResult(null);
        $response = (new Dispatcher($host))->dispatch('artifact/save', self::body('studio.operation/artifact.save', [
            'expectedRevision' => 'vector.blueprint-r1',
            'idempotencyKey' => 'idempotency/save-1',
        ]));
        $this->assertRefusalCategory($response, 'internal', 'A protected mutation must return its revision.');
        $this->assertStringContains('kumwe.producer/missing-revision', $response->body, 'Named rule.');
        $this->assertSame(0, $host->mutations->recordings, 'A contract-breaking outcome is not recorded for replay.');
    }

    public function testAnAbsentOptionalPortIsUnavailableNotGuessed(): void
    {
        $host = new FakeHost();
        $response = (new Dispatcher($host))->dispatch('telemetry/emit', self::body(
            'studio.operation/telemetry.emit',
            [],
            ['name' => 'studio.telemetry/opened']
        ));
        $this->assertRefusalCategory($response, 'unavailable', 'An absent optional port refuses.');
        $document = CanonicalJson::decode($response->body);
        $this->assertSame(false, $document->retryable, 'A structurally absent port is not retryable.');
        $this->assertSame(
            ['studio.operation/telemetry.emit'],
            $host->authorizationFake->decisions,
            'Authorization still came first.'
        );
    }
}
