<?php

declare(strict_types=1);

namespace Kumwe\Producer\Deployment;

/**
 * Transport admission for the same-origin session profile of the Studio
 * deployment contract, evaluated before any request body is read.
 * Studio's configured host calls are same-origin `fetch()` requests that
 * carry the exact document origin and the complete Fetch Metadata tuple
 * `Sec-Fetch-Site: same-origin`, `Sec-Fetch-Mode: cors`, `Sec-Fetch-Dest:
 * empty`. A navigation, a resource load, a cross-site request, or a request
 * with a missing, duplicated or partial tuple is refused with a stable code so
 * the host answers before its session or CSRF layer does any work. This is a
 * request-integrity check, not authentication or authorization: the host
 * still verifies its session, its CSRF token and every operation permission.
 * @since   0.3.0
 */
final class SameOriginFetchMetadataPolicy
{
    /**
     * The exact Fetch Metadata tuple the profile requires, by lowercase header name.
     * @var array<string, string>
     * @since   0.3.0
     */
    private const REQUIRED_METADATA = [
        'sec-fetch-site' => 'same-origin',
        'sec-fetch-mode' => 'cors',
        'sec-fetch-dest' => 'empty',
    ];

    /**
     * Bind the policy to the one document origin it admits.
     * @param string $allowedOrigin Exact `scheme://host[:port]` of the page that embeds Studio.
     * @throws DeploymentException `origin` when the value is not an exact admissible origin.
     * @since   0.3.0
     */
    public function __construct(private readonly string $allowedOrigin)
    {
        StudioContentSecurityPolicy::assertOrigin($allowedOrigin);
    }

    /**
     * Admit or refuse one request from its headers alone.
     * @param array<string, list<string>> $headers Header values keyed by name; names are
     *                                             compared case-insensitively and every value
     *                                             of a repeated header is passed, so a duplicate
     *                                             is visible and refused.
     * @return string|null Null when the request is admitted; otherwise the stable refusal
     *                     code `origin-missing`, `origin-duplicate`, `origin-mismatch`,
     *                     `fetch-site`, `fetch-mode` or `fetch-dest`.
     * @since   0.3.0
     */
    public function admit(array $headers): ?string
    {
        $normalized = [];
        foreach ($headers as $name => $values) {
            $key = strtolower(trim($name));
            foreach ($values as $value) {
                $normalized[$key][] = trim($value);
            }
        }
        $origins = $normalized['origin'] ?? [];
        if ($origins === []) {
            return 'origin-missing';
        }
        if (count($origins) !== 1) {
            return 'origin-duplicate';
        }
        if (strtolower($origins[0]) !== strtolower($this->allowedOrigin)) {
            return 'origin-mismatch';
        }
        foreach (self::REQUIRED_METADATA as $name => $expected) {
            $values = $normalized[$name] ?? [];
            if (count($values) !== 1 || strtolower($values[0]) !== $expected) {
                return substr($name, strlen('sec-'));
            }
        }

        return null;
    }
}
