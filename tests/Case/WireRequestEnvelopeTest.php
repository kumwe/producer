<?php

/**
 * Request envelope acceptance and every refusal path the wire discipline
 * requires: size bound, UTF-8, malformed JSON, duplicate members, unknown
 * and missing and malformed members, unsupported wire version, unknown
 * operation, and argument shape bounds. Refusals must stay non-disclosing:
 * the user-facing message never echoes request values.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Tests\Case;

use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Tests\TestCase;
use Kumwe\Producer\Wire\RequestEnvelope;

final class WireRequestEnvelopeTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     */
    private static function contextMembers(array $overrides = []): array
    {
        return array_merge([
            'operationId' => 'studio.operation/artifact.load',
            'protocolVersion' => RequestEnvelope::WIRE_PROTOCOL_VERSION,
            'requestId' => 'requests/test-1',
            'resourceContextKey' => 'contexts/test',
            'sessionGeneration' => 'session-r1',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $contextOverrides
     */
    private static function body(array $contextOverrides = [], bool $withArguments = true): string
    {
        $document = [];
        if ($withArguments) {
            $document['arguments'] = ['id' => 'vector.blueprint', 'version' => '1.0.0'];
        }
        $context = self::contextMembers($contextOverrides);
        foreach ($context as $name => $value) {
            if ($value === null) {
                unset($context[$name]);
            }
        }
        $document['context'] = $context;

        return json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function assertRefused(string $body, string $category, string $message, ?int $maximumBodyBytes = null): HostRefusal
    {
        $refusal = $this->assertThrows(
            static fn (): RequestEnvelope => $maximumBodyBytes === null
                ? RequestEnvelope::parse($body)
                : RequestEnvelope::parse($body, $maximumBodyBytes),
            HostRefusal::class,
            $message
        );
        $this->assertSame($category, $refusal->error()->category(), $message . ' (category)');
        $this->assertSame(false, $refusal->error()->retryable(), $message . ' (retryable)');

        return $refusal;
    }

    public function testAcceptsACompleteEnvelope(): void
    {
        $body = self::body([
            'expectedRevision' => 'vector.blueprint-r1',
            'idempotencyKey' => 'idempotency/save-1',
            'locale' => 'en-GB',
            'traceContext' => ['traceparent' => 'trace-1'],
        ]);
        $envelope = RequestEnvelope::parse($body);
        $context = $envelope->context();
        $this->assertSame('studio.operation/artifact.load', $context->operationId, 'operationId round-trips.');
        $this->assertSame(RequestEnvelope::WIRE_PROTOCOL_VERSION, $context->protocolVersion, 'protocolVersion round-trips.');
        $this->assertSame('requests/test-1', $context->requestId, 'requestId round-trips.');
        $this->assertSame('contexts/test', $context->resourceContextKey, 'resourceContextKey round-trips.');
        $this->assertSame('session-r1', $context->sessionGeneration, 'sessionGeneration round-trips.');
        $this->assertSame('vector.blueprint-r1', $context->expectedRevision, 'expectedRevision round-trips.');
        $this->assertSame('idempotency/save-1', $context->idempotencyKey, 'idempotencyKey round-trips.');
        $this->assertSame('en-GB', $context->locale, 'locale round-trips.');
        $this->assertSame(['traceparent' => 'trace-1'], $context->traceContext, 'traceContext round-trips.');
        $this->assertSame(true, $envelope->hasArguments(), 'The argument member is present.');
        $this->assertSame('vector.blueprint', $envelope->arguments()->id, 'Arguments decode to the canonical shape.');
    }

    public function testDistinguishesAbsentNullAndPresentArguments(): void
    {
        $withoutArguments = RequestEnvelope::parse(self::body([], false));
        $this->assertSame(false, $withoutArguments->hasArguments(), 'Absence is not an argument.');
        $this->assertSame(null, $withoutArguments->arguments(), 'Absent arguments read as null.');

        $nullArguments = RequestEnvelope::parse(
            '{"arguments":null,"context":' . json_encode(self::contextMembers(), JSON_UNESCAPED_SLASHES) . '}'
        );
        $this->assertSame(true, $nullArguments->hasArguments(), 'Explicit null is an argument.');
        $this->assertSame(null, $nullArguments->arguments(), 'The explicit null argument is null.');
    }

    public function testRefusesAnOversizeBodyBeforeDecoding(): void
    {
        $body = self::body();
        $this->assertRefused($body, 'limit-exceeded', 'A body above the bound must be refused.', strlen($body) - 1);
        $envelope = RequestEnvelope::parse($body, strlen($body));
        $this->assertSame(true, $envelope->hasArguments(), 'A body exactly at the bound is accepted.');
        $this->assertThrows(
            static fn (): RequestEnvelope => RequestEnvelope::parse($body, 0),
            \InvalidArgumentException::class,
            'A non-positive bound is a programming error, not a wire refusal.'
        );
    }

    public function testRefusesNonUtf8AndMalformedJson(): void
    {
        $this->assertRefused("{\"context\": \"\xff\xfe\"}", 'invalid-request', 'Invalid UTF-8 must be refused.');
        $this->assertRefused('{"context":', 'invalid-request', 'Truncated JSON must be refused.');
        $this->assertRefused('', 'invalid-request', 'An empty body must be refused.');
        $this->assertRefused('[]', 'invalid-request', 'A non-object body must be refused.');
        $this->assertRefused('"context"', 'invalid-request', 'A scalar body must be refused.');
    }

    public function testRefusesDuplicateMembersWherePhpAloneWouldKeepTheLast(): void
    {
        $context = json_encode(self::contextMembers(), JSON_UNESCAPED_SLASHES);
        $this->assertRefused(
            '{"context":' . $context . ',"context":' . $context . '}',
            'invalid-request',
            'A duplicated top-level member must be refused.'
        );
        $this->assertRefused(
            '{"arguments":{"id":"a","id":"b"},"context":' . $context . '}',
            'invalid-request',
            'A duplicated nested member must be refused.'
        );
        $escapeSpelledId = '"' . chr(92) . 'u0069d"';
        $this->assertRefused(
            '{"arguments":{"id":"a",' . $escapeSpelledId . ':"b"},"context":' . $context . '}',
            'invalid-request',
            'Escape-spelled duplicates must collide after decoding.'
        );
        $accepted = RequestEnvelope::parse(
            '{"arguments":[{"id":"a"},{"id":"b"}],"context":' . $context . '}'
        );
        $this->assertSame(
            true,
            $accepted->hasArguments(),
            'The same name in sibling objects is not a duplicate.'
        );
        $refusal = $this->assertRefused(
            '{"arguments":{"deep":{"x":1,"x":2}},"context":' . $context . '}',
            'invalid-request',
            'Duplicates at depth must be found.'
        );
        $this->assertStringExcludes(
            'deep',
            $refusal->error()->message()->defaultMessage() ?? '',
            'The user-facing message never echoes request structure.'
        );
    }

    public function testRefusesUnknownMissingAndMalformedMembers(): void
    {
        $context = json_encode(self::contextMembers(), JSON_UNESCAPED_SLASHES);
        $this->assertRefused(
            '{"extra":1,"context":' . $context . '}',
            'invalid-request',
            'An unknown top-level member must be refused.'
        );
        $this->assertRefused(
            '{"arguments":null}',
            'invalid-request',
            'A missing context must be refused.'
        );
        $this->assertRefused(
            '{"context":{"actor":"someone",' . substr($context, 1) . '}' ,
            'invalid-request',
            'An unknown context member must be refused — an actor value is display context, never authentication.'
        );
        $this->assertRefused(
            self::body(['requestId' => null]),
            'invalid-request',
            'A missing required context member must be refused.'
        );
        $this->assertRefused(
            self::body(['requestId' => '']),
            'invalid-request',
            'An empty requestId must be refused.'
        );
        $valueRefusal = $this->assertRefused(
            self::body(['requestId' => 'has space']),
            'invalid-request',
            'A requestId outside the stable identifier grammar must be refused.'
        );
        $this->assertStringExcludes(
            'has space',
            $valueRefusal->error()->toCanonicalJson(),
            'A refusal names the member, never its value.'
        );
        $this->assertRefused(
            self::body(['requestId' => 42]),
            'invalid-request',
            'A non-string requestId must be refused.'
        );
        $this->assertRefused(
            self::body(['resourceContextKey' => '__proto__']),
            'invalid-request',
            'A prototype-polluting resourceContextKey must be refused.'
        );
        $this->assertRefused(
            self::body(['sessionGeneration' => '']),
            'invalid-request',
            'An empty sessionGeneration must be refused.'
        );
        $this->assertRefused(
            self::body(['expectedRevision' => str_repeat('r', 201)]),
            'invalid-request',
            'An oversize expectedRevision must be refused.'
        );
        $this->assertRefused(
            self::body(['locale' => 'not a locale']),
            'invalid-request',
            'A malformed locale must be refused.'
        );
        $this->assertRefused(
            self::body(['idempotencyKey' => 'bad key']),
            'invalid-request',
            'A malformed idempotencyKey must be refused.'
        );
    }

    public function testRefusesTraceContextOutsideItsBounds(): void
    {
        $tooMany = [];
        for ($index = 0; $index < 11; $index++) {
            $tooMany['name' . $index] = 'value';
        }
        $this->assertRefused(
            self::body(['traceContext' => $tooMany]),
            'invalid-request',
            'More than ten trace entries must be refused.'
        );
        $this->assertRefused(
            self::body(['traceContext' => ['NotLocal' => 'value']]),
            'invalid-request',
            'A trace name outside the local-name grammar must be refused.'
        );
        $this->assertRefused(
            self::body(['traceContext' => ['constructor' => 'value']]),
            'invalid-request',
            'A prototype-polluting trace name must be refused.'
        );
        $this->assertRefused(
            self::body(['traceContext' => ['traceparent' => str_repeat('v', 201)]]),
            'invalid-request',
            'A trace value beyond 200 code points must be refused.'
        );
        $this->assertRefused(
            self::body(['traceContext' => ['traceparent' => 7]]),
            'invalid-request',
            'A non-string trace value must be refused.'
        );
    }

    public function testRefusesAnUnsupportedWireVersionAsIncompatible(): void
    {
        $refusal = $this->assertRefused(
            self::body(['protocolVersion' => '9.9.9']),
            'incompatible',
            'A wire version the producer does not speak must be refused as incompatible.'
        );
        $this->assertStringExcludes(
            '9.9.9',
            $refusal->error()->toCanonicalJson(),
            'The refusal advertises the supported version, never echoes the requested one.'
        );
        $this->assertRefused(
            self::body(['protocolVersion' => 'not-semver']),
            'invalid-request',
            'A protocolVersion outside the semantic version grammar is malformed, not incompatible.'
        );
    }

    public function testRefusesAnOperationOutsideTheClosedRegistry(): void
    {
        $this->assertRefused(
            self::body(['operationId' => 'studio.operation/artifact.delete']),
            'invalid-request',
            'An operation outside the pinned registry must be refused.'
        );
        $this->assertRefused(
            self::body(['operationId' => 'NotQualified']),
            'invalid-request',
            'An operationId outside the qualified-name grammar must be refused.'
        );
    }

    public function testRefusesArgumentsOutsideTheContractJsonShape(): void
    {
        $context = json_encode(self::contextMembers(), JSON_UNESCAPED_SLASHES);
        $this->assertRefused(
            '{"arguments":{"__proto__":1},"context":' . $context . '}',
            'invalid-request',
            'A prototype-polluting argument member must be refused.'
        );
        $this->assertRefused(
            '{"arguments":{"bad' . chr(92) . 'u0001name":1},"context":' . $context . '}',
            'invalid-request',
            'A control character in an argument member name must be refused.'
        );

        $tooManyItems = '[' . implode(',', array_fill(0, 10001, '0')) . ']';
        $this->assertRefused(
            '{"arguments":' . $tooManyItems . ',"context":' . $context . '}',
            'invalid-request',
            'An argument array beyond 10000 items must be refused.'
        );

        $members = [];
        for ($index = 0; $index < 10001; $index++) {
            $members[] = '"m' . $index . '":0';
        }
        $this->assertRefused(
            '{"arguments":{' . implode(',', $members) . '},"context":' . $context . '}',
            'invalid-request',
            'An argument object beyond 10000 members must be refused.'
        );

        $deep = str_repeat('[', 70) . '0' . str_repeat(']', 70);
        $this->assertRefused(
            '{"arguments":' . $deep . ',"context":' . $context . '}',
            'invalid-request',
            'An argument deeper than the canonical bound must be refused.'
        );

        $atBound = RequestEnvelope::parse(
            '{"arguments":' . str_repeat('[', 62) . str_repeat(']', 62) . ',"context":' . $context . '}'
        );
        $this->assertSame(true, $atBound->hasArguments(), 'Depth inside the canonical bound is accepted.');
    }
}
