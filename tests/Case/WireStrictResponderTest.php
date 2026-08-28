<?php

/**
 * Response discipline: exact canonical bytes, exact header list, pure
 * value objects with no emission side effects, and refusal of results
 * that cannot take canonical form.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Error\HostError;
use Kumwe\Producer\Error\MessageReference;
use Kumwe\Producer\Tests\TestCase;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\JsonShapeViolation;
use Kumwe\Producer\Wire\StrictResponder;

final class WireStrictResponderTest extends TestCase
{
    public function testANullValuedResultEmitsExplicitAbsence(): void
    {
        $response = (new StrictResponder())->result(new HostResult(null));
        $this->assertSame('{"value":null}', $response->body, 'Nothing is an explicit null value, never an omission.');
        $this->assertSame(false, $response->refusal, 'A result is not a refusal.');
    }

    public function testARevisionedResultOrdersMembersCanonically(): void
    {
        $value = new \stdClass();
        $value->b = [1, 2.5, true];
        $value->a = 'héllo';
        $response = (new StrictResponder())->result(new HostResult($value, 'blueprint-r2'));
        $this->assertSame(
            '{"revision":"blueprint-r2","value":{"a":"héllo","b":[1,2.5,true]}}',
            $response->body,
            'The result document is canonical: sorted members, minimal escapes, raw UTF-8.'
        );
    }

    public function testHeadersCarryTheExactContentTypeDiscipline(): void
    {
        $responder = new StrictResponder();
        $response = $responder->result(new HostResult(null));
        $this->assertSame(
            [
                'cache-control' => 'no-store',
                'content-length' => (string) strlen($response->body),
                'content-type' => 'application/json',
                'x-content-type-options' => 'nosniff',
            ],
            $response->headers,
            'The header list is exact: canonical media type, no sniffing, no caching.'
        );
        $refusal = $responder->refusal(HostError::notFound(
            new MessageReference('studio.host/unknown-resource', 'No such resource is visible to you.')
        ));
        $this->assertSame(
            $refusal->headers['content-length'],
            (string) strlen($refusal->body),
            'Refusal headers measure the refusal body.'
        );
        $this->assertSame('application/json', $refusal->headers['content-type'], 'Refusals share the media type.');
    }

    public function testARefusalBodyIsTheCanonicalErrorDocument(): void
    {
        $error = HostError::conflict(
            new MessageReference('studio.host/save-conflict', 'Another revision was accepted while you were editing.'),
            'blueprint-r9'
        );
        $response = (new StrictResponder())->refusal($error);
        $this->assertSame(true, $response->refusal, 'A refusal is flagged as one.');
        $this->assertSame($error->toCanonicalJson(), $response->body, 'The body is exactly the canonical error.');
        $this->assertStringContains('"category":"conflict"', $response->body, 'The category is on the wire.');
        $this->assertStringContains('"revision":"blueprint-r9"', $response->body, 'The safe revision is on the wire.');
    }

    public function testResultsOutsideTheContractShapeAreRefusedAtConstruction(): void
    {
        $this->assertThrows(
            static fn (): HostResult => new HostResult(NAN),
            JsonShapeViolation::class,
            'A non-finite number must be refused.'
        );
        $this->assertThrows(
            static fn (): HostResult => new HostResult(['assoc' => 'array']),
            JsonShapeViolation::class,
            'An associative PHP array must be refused, not guessed at.'
        );
        $this->assertThrows(
            static fn (): HostResult => new HostResult(fopen('php://memory', 'r')),
            JsonShapeViolation::class,
            'A resource is unrepresentable.'
        );
        $forbidden = new \stdClass();
        $forbidden->{'__proto__'} = 1;
        $this->assertThrows(
            static fn (): HostResult => new HostResult($forbidden),
            JsonShapeViolation::class,
            'A prototype-polluting member must be refused.'
        );
        $this->assertThrows(
            static fn (): HostResult => new HostResult(null, ''),
            \InvalidArgumentException::class,
            'An empty revision must be refused.'
        );
    }

    public function testRespondingIsPure(): void
    {
        $responder = new StrictResponder();
        $level = ob_get_level();
        ob_start();
        $responder->result(new HostResult('plain'));
        $responder->refusal(HostError::internal(new MessageReference('studio.host/internal')));
        $emitted = ob_get_clean();
        $this->assertSame('', $emitted, 'The responder never echoes; the host does the emission.');
        $this->assertSame($level, ob_get_level(), 'Output buffering is untouched.');
    }
}
