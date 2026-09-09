<?php

declare(strict_types=1);

namespace Kumwe\Producer\Deployment;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Schema\StudioContractResources;

/**
 * The exact Content-Security-Policy values the pinned Studio asset manifest
 * publishes for its two browser surfaces, with the host's exact extra script
 * origins folded in.
 * The authoring surface policy is the manifest's `headerTemplate` with its
 * `{{STYLE_NONCE}}` placeholder replaced by a fresh per-response nonce of at
 * least the manifest's minimum entropy; the published-page surface policy is
 * the manifest's enhancement-runtime baseline. When the assets are served
 * from another origin (a registry CDN or a mirror), that exact origin — and
 * nothing wider — is appended to `script-src`. Wildcards, scheme-wide sources
 * and `unsafe-*` keywords are refused.
 * @since   0.3.0
 */
final class StudioContentSecurityPolicy
{
    /**
     * Loopback hosts that may be admitted over plain HTTP during development.
     * @var list<string>
     * @since   0.3.0
     */
    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    /**
     * Not constructable: the policies are functions of the pinned manifest.
     * @since   0.3.0
     */
    private function __construct()
    {
    }

    /**
     * The authoring-surface policy for one response.
     * @param string       $styleNonce    Fresh base64 nonce (standard or URL-safe alphabet) that
     *                                    decodes to at least the manifest's minimum entropy; the
     *                                    host places the same value on the trusted `<style>`
     *                                    elements of that response only.
     * @param list<string> $scriptOrigins Exact `scheme://host[:port]` origins that serve the
     *                                    authoring module when it is not same-origin.
     * @throws DeploymentException `nonce` for a short or malformed nonce, `origin` for an
     *                             inadmissible origin, `manifest` when the pinned manifest
     *                             does not publish the policy.
     * @throws \RuntimeException    When the installed Studio contract bytes are missing or drifted.
     * @since   0.3.0
     */
    public static function authoring(string $styleNonce, array $scriptOrigins = []): string
    {
        $manifest = self::manifest();
        $policy = $manifest->contentSecurityPolicy ?? null;
        $template = $policy instanceof \stdClass ? ($policy->headerTemplate ?? null) : null;
        $nonceRule = $policy instanceof \stdClass ? ($policy->styleNonce ?? null) : null;
        $placeholder = $nonceRule instanceof \stdClass ? ($nonceRule->placeholder ?? null) : null;
        $minimumBits = $nonceRule instanceof \stdClass ? ($nonceRule->minimumEntropyBits ?? null) : null;
        if (
            !is_string($template)
            || !is_string($placeholder)
            || $placeholder === ''
            || !str_contains($template, $placeholder)
            || !is_int($minimumBits)
            || $minimumBits < 1
        ) {
            throw new DeploymentException('manifest', 'The pinned asset manifest publishes no authoring policy.');
        }
        if (
            preg_match('/^[A-Za-z0-9+\/_-]{16,200}={0,2}$/', $styleNonce) !== 1
            || strlen((string) base64_decode(strtr($styleNonce, '-_', '+/'), true)) * 8 < $minimumBits
        ) {
            throw new DeploymentException('nonce', 'A style nonce must carry the manifest minimum entropy.');
        }

        return self::withScriptOrigins(str_replace($placeholder, $styleNonce, $template), $scriptOrigins);
    }

    /**
     * The published-page enhancement-runtime baseline policy. A published
     * response may append only the exact content sources its own server-
     * rendered page needs; the four runtime directives stay as published.
     * @param list<string> $scriptOrigins Exact origins that serve the enhancement runtime when
     *                                    it is not same-origin.
     * @throws DeploymentException `origin` for an inadmissible origin, `manifest` when the
     *                             pinned manifest does not publish the policy.
     * @throws \RuntimeException    When the installed Studio contract bytes are missing or drifted.
     * @since   0.3.0
     */
    public static function enhancement(array $scriptOrigins = []): string
    {
        $manifest = self::manifest();
        $runtime = $manifest->enhancementRuntime ?? null;
        $policy = $runtime instanceof \stdClass ? ($runtime->contentSecurityPolicy ?? null) : null;
        if (!is_string($policy) || $policy === '') {
            throw new DeploymentException('manifest', 'The pinned asset manifest publishes no enhancement policy.');
        }

        return self::withScriptOrigins($policy, $scriptOrigins);
    }

    /**
     * Admit one exact origin for a `script-src` source expression.
     * @param string $origin Candidate `scheme://host[:port]`.
     * @throws DeploymentException `origin` for a wildcard, a path, credentials, a query, an
     *                             inadmissible scheme or a non-loopback plain-HTTP host.
     * @since   0.3.0
     */
    public static function assertOrigin(string $origin): void
    {
        $parts = parse_url($origin);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;
        if (
            str_contains($origin, '*')
            || !is_array($parts)
            || !is_string($scheme)
            || !is_string($host)
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['path'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || preg_match('/^[a-z0-9.\[\]:-]+$/i', $host) !== 1
        ) {
            throw new DeploymentException('origin', 'A script origin must be an exact scheme://host[:port].');
        }
        $scheme = strtolower($scheme);
        if ($scheme !== 'https' && ($scheme !== 'http' || !in_array(strtolower($host), self::LOOPBACK_HOSTS, true))) {
            throw new DeploymentException('origin', 'Script origins use HTTPS, or plain HTTP only on a loopback host.');
        }
    }

    /**
     * Append exact origins to the policy's `script-src` directive.
     * @param string       $policy  Published policy text.
     * @param list<string> $origins Exact origins to admit.
     * @throws DeploymentException `origin` when an origin is inadmissible or the policy has no
     *                             `script-src` directive.
     * @since   0.3.0
     */
    private static function withScriptOrigins(string $policy, array $origins): string
    {
        if ($origins === []) {
            return $policy;
        }
        $sources = [];
        foreach ($origins as $origin) {
            self::assertOrigin($origin);
            $sources[$origin] = true;
        }
        $directives = array_map('trim', explode(';', $policy));
        $found = false;
        foreach ($directives as $index => $directive) {
            if (str_starts_with($directive, 'script-src ') || $directive === 'script-src') {
                $directives[$index] = rtrim($directive) . ' ' . implode(' ', array_keys($sources));
                $found = true;
            }
        }
        if (!$found) {
            throw new DeploymentException('origin', 'The published policy has no script-src directive to widen.');
        }

        return implode('; ', $directives);
    }

    /**
     * Decode the manifest-verified asset manifest once per process.
     * @throws \RuntimeException When the installed manifest bytes are not a canonical object.
     * @since   0.3.0
     */
    private static function manifest(): \stdClass
    {
        /** @var \stdClass|null $shared */
        static $shared = null;
        if ($shared === null) {
            $decoded = CanonicalJson::decode(StudioContractResources::browserManifestBytes());
            if (!$decoded instanceof \stdClass) {
                throw new \RuntimeException('The installed Studio asset manifest is not a JSON object.');
            }
            $shared = $decoded;
        }

        return $shared;
    }
}
