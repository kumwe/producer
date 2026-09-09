<?php

declare(strict_types=1);

namespace Kumwe\Producer\Deployment;

use Kumwe\Producer\Canonical\CanonicalEncodingException;
use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Schema\StudioContractRelease;
use Kumwe\Producer\Schema\StudioDocumentSchemaRegistry;
use Kumwe\Producer\Wire\OperationRegistry;

/**
 * Emit one inert Studio browser deployment: the mount target and its
 * associated `application/json` configuration block.
 * The prebuilt Studio module discovers `[data-kumwe-studio]` targets and reads
 * the referenced block through `textContent`; nothing here is executable, so
 * the block needs no script nonce and the page policy stays `script-src`
 * without `unsafe-inline`. Before a byte is written the document is proven
 * against the pinned `studio-deployment` schema, bound to the exact pinned
 * release, bounded to the browser bootstrap's 2 MiB / depth-16 allocation,
 * checked to select its own target, and — for a hosted instance — checked for
 * launch/session context agreement and for a route map that equals the
 * operations the session advertises. Authority is never granted here: the
 * host resolves `session` from its own authentication and policy, and PHP
 * re-authorizes every later request.
 * @since   0.3.0
 */
final class StudioDeploymentEmitter
{
    /**
     * Decoded UTF-8 byte ceiling of one deployment document, matching the
     * browser bootstrap's allocation bound.
     * @since   0.3.0
     */
    public const MAXIMUM_BYTES = 2097152;

    /**
     * Nesting ceiling of one deployment document, matching the browser bootstrap.
     * @since   0.3.0
     */
    public const MAXIMUM_DEPTH = 16;

    /**
     * Element identifier grammar admitted for the mount and configuration ids.
     * @since   0.3.0
     */
    private const IDENTIFIER_PATTERN = '/^[A-Za-z][A-Za-z0-9_-]{0,199}$/';

    /**
     * Bind the emitter to the pinned schema authority and release coordinates.
     * @param StudioDocumentSchemaRegistry $schemas Compiled pinned corpus.
     * @param StudioContractRelease        $release Exact coordinated release the emitted
     *                                              document must bind.
     * @since   0.3.0
     */
    public function __construct(
        private readonly StudioDocumentSchemaRegistry $schemas,
        private readonly StudioContractRelease $release,
    ) {
    }

    /**
     * The exact `release` object every emitted document must carry, copied
     * from the pinned release so a cached module and a configuration for
     * another release fail closed in the browser.
     * @since   0.3.0
     */
    public function releaseBinding(): \stdClass
    {
        $binding = new \stdClass();
        $binding->version = $this->release->release();
        $binding->corpusManifestDigest = $this->release->corpusManifestDigest();

        return $binding;
    }

    /**
     * Render the discovery pair for one mount: the target `div` and the inert
     * configuration block, separated by one line feed.
     * @param string    $mountId         Unique `id` of the target element; the document's
     *                                   `mount` selector must be exactly `#` followed by it.
     * @param string    $configurationId Unique `id` of the configuration block, referenced
     *                                   from the target's `data-kumwe-studio` attribute.
     * @param \stdClass $configuration   Decoded `studio-deployment` document.
     * @throws DeploymentException `identifier` for an unusable or duplicated id, or any
     *                             refusal {@see document()} raises.
     * @since   0.3.0
     */
    public function render(string $mountId, string $configurationId, \stdClass $configuration): string
    {
        foreach ([$mountId, $configurationId] as $identifier) {
            if (preg_match(self::IDENTIFIER_PATTERN, $identifier) !== 1) {
                throw new DeploymentException('identifier', 'A Studio mount id must be a bounded HTML identifier.');
            }
        }
        if ($mountId === $configurationId) {
            throw new DeploymentException('identifier', 'The Studio mount and configuration ids must differ.');
        }
        $json = $this->document($mountId, $configuration);

        return sprintf(
            '<div id="%1$s" data-kumwe-studio="%2$s"></div>' . "\n"
            . '<script id="%2$s" type="application/json">%3$s</script>',
            $mountId,
            $configurationId,
            $json,
        );
    }

    /**
     * Prove one deployment document and return its escaped canonical JSON,
     * safe for a raw-text `application/json` script element.
     * A host that renders the target element itself (to add classes, labels
     * or attributes) places this text inside its own
     * `<script id="…" type="application/json">` and references that id from
     * the target's `data-kumwe-studio` attribute.
     * @param string    $mountId       `id` of the target element the document must select.
     * @param \stdClass $configuration Decoded `studio-deployment` document.
     * @throws DeploymentException `identifier` for an unusable id; `kind` when the document
     *                             is not a deployment; `release-mismatch` when it binds
     *                             another release; `oversized` past the byte or depth
     *                             bound; `schema-invalid` on any pinned-schema refusal;
     *                             `mount-mismatch` when the selector is not `#mountId`;
     *                             `resource-context` when a hosted launch and session
     *                             disagree; `protocol` when the session protocol is not
     *                             advertised; `routing-mismatch` when the route map and the
     *                             advertised operations differ. The pinned schema itself
     *                             refuses hosted members on a standalone document.
     * @since   0.3.0
     */
    public function document(string $mountId, \stdClass $configuration): string
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $mountId) !== 1) {
            throw new DeploymentException('identifier', 'A Studio mount id must be a bounded HTML identifier.');
        }
        if (($configuration->kind ?? null) !== 'studio-deployment') {
            throw new DeploymentException('kind', 'Only a studio-deployment document can be emitted.');
        }
        $release = $configuration->release ?? null;
        if (
            !$release instanceof \stdClass
            || ($release->version ?? null) !== $this->release->release()
            || ($release->corpusManifestDigest ?? null) !== $this->release->corpusManifestDigest()
            || count(get_object_vars($release)) !== 2
        ) {
            throw new DeploymentException('release-mismatch', 'The deployment must bind the exact pinned Studio release.');
        }
        try {
            $json = CanonicalJson::stringify($configuration, self::MAXIMUM_DEPTH);
        } catch (CanonicalEncodingException $error) {
            throw new DeploymentException(
                $error->rejection() === 'depth-exceeded' ? 'oversized' : 'schema-invalid',
                'The deployment document is not bounded canonical JSON.',
            );
        }
        if (strlen($json) > self::MAXIMUM_BYTES) {
            throw new DeploymentException('oversized', 'The deployment document exceeds the browser allocation bound.');
        }
        $validation = $this->schemas->validate('studio-deployment', $configuration);
        if (!$validation->valid()) {
            throw new DeploymentException('schema-invalid', 'The deployment document does not satisfy its pinned schema.');
        }
        if (($configuration->mount ?? null) !== '#' . $mountId) {
            throw new DeploymentException('mount-mismatch', 'The deployment must select exactly its own target element.');
        }
        $transport = $configuration->transport ?? null;
        if ($transport instanceof \stdClass && ($transport->kind ?? null) === 'http') {
            self::assertHosted($configuration, $transport);
        }

        return str_replace(
            ['<', '>', '&', "\u{2028}", "\u{2029}"],
            ['\u003c', '\u003e', '\u0026', '\u2028', '\u2029'],
            $json,
        );
    }

    /**
     * Enforce the hosted invariants the browser bootstrap checks before its
     * first request, so a misconfigured page fails at emission rather than in
     * the editor.
     * @param \stdClass $configuration Schema-valid deployment document.
     * @param \stdClass $transport     Its `http` transport member.
     * @throws DeploymentException `resource-context`, `protocol` or `routing-mismatch`.
     * @since   0.3.0
     */
    private static function assertHosted(\stdClass $configuration, \stdClass $transport): void
    {
        $launch = $configuration->launch ?? null;
        $session = $configuration->session ?? null;
        if (!$launch instanceof \stdClass || !$session instanceof \stdClass) {
            throw new DeploymentException('resource-context', 'A hosted deployment requires launch and session.');
        }
        $launchContext = $launch->resourceContext ?? null;
        $sessionContext = $session->resourceContext ?? null;
        if (
            !$launchContext instanceof \stdClass
            || !$sessionContext instanceof \stdClass
            || CanonicalJson::stringify($launchContext) !== CanonicalJson::stringify($sessionContext)
        ) {
            throw new DeploymentException('resource-context', 'The launch and session resource contexts must agree.');
        }
        $capabilities = $session->hostCapabilities ?? null;
        $protocols = $capabilities instanceof \stdClass ? ($capabilities->protocolVersions ?? null) : null;
        if (!is_array($protocols) || !in_array($session->protocolVersion ?? null, $protocols, true)) {
            throw new DeploymentException('protocol', 'The session protocol must be advertised by its host capabilities.');
        }
        $routing = $transport->routing ?? null;
        if (!$routing instanceof \stdClass || ($routing->kind ?? null) !== 'operation-map') {
            return;
        }
        $advertised = [];
        $ports = $capabilities instanceof \stdClass ? ($capabilities->ports ?? null) : null;
        foreach (is_array($ports) ? $ports : [] as $port) {
            $operations = $port instanceof \stdClass ? ($port->operations ?? null) : null;
            foreach (is_array($operations) ? $operations : [] as $capability) {
                if (!is_string($capability) || !OperationRegistry::isCapability($capability)) {
                    throw new DeploymentException('routing-mismatch', 'An advertised operation is not on the pinned wire.');
                }
                $advertised[] = OperationRegistry::byCapability($capability)->route;
            }
        }
        $endpoints = $routing->endpoints ?? null;
        $routed = $endpoints instanceof \stdClass ? array_map('strval', array_keys(get_object_vars($endpoints))) : [];
        sort($advertised, SORT_STRING);
        sort($routed, SORT_STRING);
        if (array_values(array_unique($advertised)) !== $routed) {
            throw new DeploymentException(
                'routing-mismatch',
                'The operation map must name exactly the operations the session advertises.',
            );
        }
    }
}
