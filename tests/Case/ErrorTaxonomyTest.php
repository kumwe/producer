<?php

/**
 * The closed host error taxonomy: construction of all twelve categories,
 * the schema's semantic invariants, and canonical byte stability against
 * the pinned contract's own conflict example.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Error\Diagnostic;
use Kumwe\Producer\Error\DiagnosticLocation;
use Kumwe\Producer\Error\HostError;
use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Error\MessageReference;
use Kumwe\Producer\Tests\TestCase;

final class ErrorTaxonomyTest extends TestCase
{
    private const CONFLICT_EXAMPLE_CANONICAL = '{"category":"conflict","contractVersion":"0.1-draft",'
        . '"correlationId":"requests/2f6c1f6d","diagnostics":[{"code":"studio.host/expected-revision-mismatch",'
        . '"location":{"artifactId":"landing.blueprint"},"message":{"defaultMessage":'
        . '"The save expected revision blueprint-r8.","key":"studio.host/expected-revision-mismatch"},'
        . '"severity":"error"}],"kind":"host-error","message":{"defaultMessage":'
        . '"Another revision was accepted while you were editing.","key":"studio.host/save-conflict"},'
        . '"retryable":false,"revision":"blueprint-r9"}';

    public function testEveryCategoryConstructsWithItsFixedRetryClassification(): void
    {
        $message = new MessageReference('kumwe.producer/test', 'A test refusal.');
        $expectations = [
            'invalid-request' => [HostError::invalidRequest($message), false],
            'unauthenticated' => [HostError::unauthenticated($message), false],
            'forbidden' => [HostError::forbidden($message), false],
            'not-found' => [HostError::notFound($message), false],
            'conflict' => [HostError::conflict($message, 'r7'), false],
            'validation-failed' => [HostError::validationFailed($message), false],
            'incompatible' => [HostError::incompatible($message), false],
            'limit-exceeded' => [HostError::limitExceeded($message), false],
            'rate-limited' => [HostError::rateLimited($message), true],
            'unavailable' => [HostError::unavailable($message), false],
            'cancelled' => [HostError::cancelled($message), false],
            'internal' => [HostError::internal($message), false],
        ];
        $this->assertSame(HostError::CATEGORIES, array_keys($expectations), 'The taxonomy must stay closed at twelve.');
        foreach ($expectations as $category => [$error, $retryable]) {
            $this->assertSame($category, $error->category(), "{$category} must carry its own category.");
            $this->assertSame($retryable, $error->retryable(), "{$category} must fix its retry classification.");
            $document = $error->toDocument();
            $this->assertSame('host-error', $document->kind, "{$category} must be a host-error document.");
            $this->assertSame(HostError::CONTRACT_VERSION, $document->contractVersion, "{$category} must pin the contract version.");
        }
    }

    public function testConflictRequiresTheSafeCurrentRevisionAndOthersRefuseOne(): void
    {
        $message = new MessageReference('kumwe.producer/test', 'A test refusal.');
        $conflict = HostError::conflict($message, 'blueprint-r9');
        $this->assertSame('blueprint-r9', $conflict->revision(), 'A conflict must return the safe current revision.');
        $this->assertSame('blueprint-r9', $conflict->toDocument()->revision, 'The document must carry the revision.');

        $this->assertThrows(
            static fn (): HostError => HostError::conflict($message, ''),
            \InvalidArgumentException::class,
            'An empty revision must be refused.'
        );
        $this->assertThrows(
            static fn (): HostError => HostError::conflict($message, str_repeat('r', 201)),
            \InvalidArgumentException::class,
            'An oversize revision must be refused.'
        );
        foreach (
            [
                HostError::invalidRequest($message),
                HostError::rateLimited($message),
                HostError::internal($message),
            ] as $error
        ) {
            $this->assertTrue(
                !property_exists($error->toDocument(), 'revision'),
                'Only a conflict carries a revision member.'
            );
        }
    }

    public function testRetryHintsAreTiedToRetryableRefusals(): void
    {
        $message = new MessageReference('kumwe.producer/test', 'A test refusal.');

        $limited = HostError::rateLimited($message, 60000);
        $this->assertSame(60000, $limited->retryAfterMilliseconds(), 'rate-limited must accept a retry hint.');
        $this->assertSame(true, $limited->retryable(), 'rate-limited is retryable by definition.');

        $transient = HostError::unavailable($message, true, 1500);
        $this->assertSame(1500, $transient->retryAfterMilliseconds(), 'A retryable outage may hint.');

        $this->assertThrows(
            static fn (): HostError => HostError::unavailable($message, false, 1500),
            \InvalidArgumentException::class,
            'A retry hint on a non-retryable unavailability must be refused.'
        );
        $this->assertThrows(
            static fn (): HostError => HostError::rateLimited($message, -1),
            \InvalidArgumentException::class,
            'A negative retry hint must be refused.'
        );
        $this->assertThrows(
            static fn (): HostError => HostError::rateLimited($message, HostError::MAXIMUM_RETRY_AFTER_MILLISECONDS + 1),
            \InvalidArgumentException::class,
            'A retry hint beyond one day must be refused.'
        );
    }

    public function testMessageReferencesHoldTheContractGrammar(): void
    {
        $this->assertThrows(
            static fn (): MessageReference => new MessageReference('NoSlash'),
            \InvalidArgumentException::class,
            'A message key must be a qualified name.'
        );
        $this->assertThrows(
            static fn (): MessageReference => new MessageReference('Upper/case'),
            \InvalidArgumentException::class,
            'A message key must be lowercase.'
        );
        $this->assertThrows(
            static fn (): MessageReference => new MessageReference('studio.host/ok', ''),
            \InvalidArgumentException::class,
            'An empty default message must be refused.'
        );
        $this->assertThrows(
            static fn (): MessageReference => new MessageReference('studio.host/ok', str_repeat('m', 501)),
            \InvalidArgumentException::class,
            'An oversize default message must be refused.'
        );
        $this->assertThrows(
            static fn (): MessageReference => new MessageReference('studio.host/ok', "\xff\xfe"),
            \InvalidArgumentException::class,
            'A non-UTF-8 default message must be refused.'
        );
        $reference = new MessageReference('studio.host/ok');
        $this->assertTrue(
            !property_exists($reference->toDocument(), 'defaultMessage'),
            'An absent default message must stay absent, not null.'
        );
    }

    public function testCorrelationIdMustBeAStableIdentifier(): void
    {
        $message = new MessageReference('kumwe.producer/test', 'A test refusal.');
        $error = HostError::forbidden($message, [], 'requests/2f6c1f6d');
        $this->assertSame('requests/2f6c1f6d', $error->correlationId(), 'A stable correlation id is kept.');
        foreach (['', ' spaced', '__proto__', str_repeat('a', 241)] as $bad) {
            $this->assertThrows(
                static fn (): HostError => HostError::forbidden($message, [], $bad),
                \InvalidArgumentException::class,
                'A malformed correlationId must be refused.'
            );
        }
    }

    public function testDiagnosticsEnforceTheirBoundedStructure(): void
    {
        $message = new MessageReference('kumwe.producer/test', 'A test refusal.');
        $this->assertThrows(
            static fn (): Diagnostic => new Diagnostic('kumwe.producer/x', 'fatal', $message),
            \InvalidArgumentException::class,
            'An unknown severity must be refused.'
        );
        $this->assertThrows(
            static fn (): Diagnostic => new Diagnostic('NotQualified', 'error', $message),
            \InvalidArgumentException::class,
            'A diagnostic code must be a qualified name.'
        );
        $tooManyParameters = [];
        for ($index = 0; $index < 21; $index++) {
            $tooManyParameters['p' . $index] = $index;
        }
        $this->assertThrows(
            static fn (): Diagnostic => new Diagnostic('kumwe.producer/x', 'error', $message, null, $tooManyParameters),
            \InvalidArgumentException::class,
            'More than 20 parameters must be refused.'
        );
        $this->assertThrows(
            static fn (): Diagnostic => new Diagnostic('kumwe.producer/x', 'error', $message, null, ['__proto__' => 1]),
            \InvalidArgumentException::class,
            'A prototype-polluting parameter name must be refused.'
        );
        $this->assertThrows(
            static fn (): Diagnostic => new Diagnostic('kumwe.producer/x', 'error', $message, null, ['list' => [1]]),
            \InvalidArgumentException::class,
            'A non-scalar parameter must be refused.'
        );
        $this->assertThrows(
            static fn (): Diagnostic => new Diagnostic('kumwe.producer/x', 'error', $message, null, [], ['a/b', 'c/d', 'e/f', 'g/h', 'i/j', 'k/l', 'm/n', 'o/p', 'q/r', 's/t', 'u/v']),
            \InvalidArgumentException::class,
            'More than 10 remediations must be refused.'
        );
        $this->assertThrows(
            static fn (): DiagnosticLocation => new DiagnosticLocation(null, null, array_fill(0, 33, 'segment')),
            \InvalidArgumentException::class,
            'A fieldPath beyond 32 segments must be refused.'
        );
        $this->assertThrows(
            static fn (): DiagnosticLocation => new DiagnosticLocation(null, null, null, str_repeat('p', 1001)),
            \InvalidArgumentException::class,
            'A jsonPointer beyond 1000 code points must be refused.'
        );

        $message2 = new MessageReference('kumwe.producer/test', 'A test refusal.');
        $tooMany = array_fill(0, 1001, new Diagnostic('kumwe.producer/x', 'error', $message2));
        $this->assertThrows(
            static fn (): HostError => HostError::validationFailed($message2, $tooMany),
            \InvalidArgumentException::class,
            'More than 1000 diagnostics must be refused.'
        );
    }

    public function testCanonicalBytesMatchThePinnedConflictExample(): void
    {
        $error = HostError::conflict(
            new MessageReference('studio.host/save-conflict', 'Another revision was accepted while you were editing.'),
            'blueprint-r9',
            [new Diagnostic(
                'studio.host/expected-revision-mismatch',
                'error',
                new MessageReference('studio.host/expected-revision-mismatch', 'The save expected revision blueprint-r8.'),
                new DiagnosticLocation('landing.blueprint')
            )],
            'requests/2f6c1f6d'
        );
        $this->assertSame(
            self::CONFLICT_EXAMPLE_CANONICAL,
            $error->toCanonicalJson(),
            'The emitted bytes must match the pinned contract example canonically.'
        );
        $this->assertSame(
            'sha256-m9ls9keJkJfs4m4TzPC6GlTsK5GUKY07PXREM9S9V2E=',
            CanonicalJson::digest(CanonicalJson::decode($error->toCanonicalJson())),
            'The digest of the emitted document must be stable.'
        );
    }

    public function testCanonicalOutputIsAFixedPointAcrossRebuilds(): void
    {
        $build = static fn (): HostError => HostError::rateLimited(
            new MessageReference('studio.host/rate-limited', 'Too many requests in this window.'),
            60000,
            [new Diagnostic(
                'studio.host/rate-window',
                'warning',
                new MessageReference('studio.host/rate-window', 'The window resets shortly.'),
                null,
                ['windowMilliseconds' => 60000, 'limit' => 1]
            )],
            'requests/rl-1'
        );
        $this->assertSame(
            $build()->toCanonicalJson(),
            $build()->toCanonicalJson(),
            'Identical construction must yield identical bytes.'
        );
        $document = CanonicalJson::decode($build()->toCanonicalJson());
        $this->assertSame(
            $build()->toCanonicalJson(),
            CanonicalJson::stringify($document),
            'Canonical output must be a fixed point of decode.'
        );
    }

    public function testHostRefusalCarriesOnlyTheStructuredError(): void
    {
        $error = HostError::forbidden(new MessageReference('studio.host/forbidden', 'This operation is not allowed.'));
        $refusal = new HostRefusal($error);
        $this->assertSame($error, $refusal->error(), 'The wrapper must hand back the canonical error.');
        $this->assertSame(
            'This operation is not allowed.',
            $refusal->getMessage(),
            'The exception message is the bounded pre-written fallback, nothing else.'
        );
        $keyOnly = new HostRefusal(HostError::internal(new MessageReference('studio.host/internal')));
        $this->assertSame(
            'studio.host/internal',
            $keyOnly->getMessage(),
            'Without a fallback the exception message is the catalog key.'
        );
    }
}
