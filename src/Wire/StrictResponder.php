<?php

/**
 * Turn outcomes into canonical response bytes with strict content-type
 * discipline.
 *
 * Pure functions: no header() calls, no echo, no I/O — the host performs
 * the actual emission. Every body is the canonical JSON of a schema-shaped
 * document; every header list carries the exact JSON media type, forbids
 * MIME sniffing, and forbids caching, because port responses can hold
 * private resource data.
 *
 * A refusal always serializes — HostError is canonical by construction. A
 * result that cannot take canonical form despite the construction-time
 * guard is converted to an internal refusal that discloses nothing, so
 * this responder never lets a half-serialized body reach the wire.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire;

use Kumwe\Producer\Canonical\CanonicalEncodingException;
use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Error\HostError;
use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Error\MessageReference;

final class StrictResponder
{
    public const CONTENT_TYPE = 'application/json';

    /**
     * @throws HostRefusal internal when the host's result cannot take canonical form
     */
    public function result(HostResult $result): Response
    {
        try {
            $body = CanonicalJson::stringify($result->toDocument());
        } catch (CanonicalEncodingException) {
            throw new HostRefusal(HostError::internal(new MessageReference(
                'kumwe.producer/unrepresentable-result',
                'The host produced a result the canonical wire form cannot represent.'
            )));
        }

        return new Response($body, self::headers($body), false);
    }

    public function refusal(HostError $error): Response
    {
        $body = $error->toCanonicalJson();

        return new Response($body, self::headers($body), true);
    }

    /**
     * @return array<string, string>
     */
    private static function headers(string $body): array
    {
        return [
            'cache-control' => 'no-store',
            'content-length' => (string) strlen($body),
            'content-type' => self::CONTENT_TYPE,
            'x-content-type-options' => 'nosniff',
        ];
    }
}
