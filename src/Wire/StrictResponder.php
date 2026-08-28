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

/**
 * The one path from an outcome to wire bytes: canonical JSON body, fixed
 * security headers, no partial output.
 *
 * Pure and deterministic — no emission, no I/O, no clock. Every response
 * it builds carries the exact JSON media type, `nosniff`, `no-store`, and
 * the byte-accurate content length; a result that cannot canonicalize
 * becomes a non-disclosing internal refusal instead of a broken body.
 *
 * @since   0.1.0
 */
final class StrictResponder
{
    /**
     * The exact media type every wire response declares; the contract
     * knows no other.
     *
     * @since   0.1.0
     */
    public const CONTENT_TYPE = 'application/json';

    /**
     * The canonical response for a successful outcome.
     *
     * @param   HostResult  $result  The proven outcome to serialize.
     *
     * @return  Response  The result document's canonical bytes, refusal
     *                    false.
     *
     * @throws  HostRefusal  internal when the host's result cannot take canonical form
     *
     * @since   0.1.0
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

    /**
     * The canonical response for a refusal. Never throws: a HostError is
     * canonical by construction, so every refusal serializes.
     *
     * @param   HostError  $error  The taxonomy refusal to serialize.
     *
     * @return  Response  The error document's canonical bytes, refusal
     *                    true.
     *
     * @since   0.1.0
     */
    public function refusal(HostError $error): Response
    {
        $body = $error->toCanonicalJson();

        return new Response($body, self::headers($body), true);
    }

    /**
     * The fixed header list for a body: exact media type, sniffing
     * forbidden, caching forbidden (port responses can hold private
     * resource data), and the byte-accurate length.
     *
     * @param   string  $body  The canonical bytes being sent.
     *
     * @return  array<string, string>  Headers under lowercase names.
     *
     * @since   0.1.0
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
