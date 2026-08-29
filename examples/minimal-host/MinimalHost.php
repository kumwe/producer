<?php

/**
 * The minimal in-memory demonstration host, in one file.
 *
 * Everything the host agreement says a host supplies, reduced to fixtures:
 * an authorization that allows exactly one demonstration actor, an
 * single-process mutation boundary with keyed replay in a PHP array, and an
 * artifact port holding one
 * composition. It exists so the host guide's claims are runnable and
 * testable on a clean clone — it is a demonstration, never a production
 * host, and it deliberately implements nothing durable.
 *
 * The file registers a library autoloader so the example runs without any
 * composer install, exactly like the test suite.
 */

declare(strict_types=1);

namespace Kumwe\ProducerExamples;

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Css\ThemeStylesheet;
use Kumwe\Producer\Error\HostError;
use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Error\MessageReference;
use Kumwe\Producer\Render\CompositionRenderer;
use Kumwe\Producer\Render\RenderResult;
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

spl_autoload_register(static function (string $class): void {
    $prefix = 'Kumwe\\Producer\\';
    if (str_starts_with($class, $prefix)) {
        $path = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

/**
 * The demonstration fixtures: one published composition and one theme.
 *
 * The composition exercises several catalog block types — section, heading,
 * rich text, grid, card, notice — plus one type outside the catalog, so the
 * page shows the bounded unknown-type fallback. Every binding is a stored
 * static value, so the composition renders with no host resolvers at all.
 */
final class DemoFixtures
{
    public const ARTIFACT_ID = 'examples/welcome';

    private const COMPOSITION_JSON = <<<'JSON'
{
  "kind": "blueprint",
  "id": "examples/welcome",
  "version": "1.0.0",
  "roots": [
    {
      "authoring": { "mode": "structural" },
      "bindings": {},
      "id": "welcome-section",
      "properties": {},
      "slots": {
        "content": [
          {
            "authoring": { "mode": "content" },
            "bindings": {
              "text": { "source": { "kind": "static-value", "value": "Studio designs it. Producer makes it real." } }
            },
            "id": "welcome-heading",
            "properties": { "level": 1 },
            "slots": {},
            "type": "studio.core/heading",
            "version": "1.0.0"
          },
          {
            "authoring": { "mode": "content" },
            "bindings": {
              "content": {
                "source": {
                  "kind": "static-value",
                  "value": {
                    "type": "doc",
                    "content": [
                      {
                        "type": "paragraph",
                        "content": [
                          {
                            "type": "text",
                            "text": "This page is a published Studio composition, rendered to semantic HTML by Kumwe Producer and served by a deliberately tiny in-memory host."
                          }
                        ]
                      }
                    ]
                  }
                }
              }
            },
            "id": "welcome-intro",
            "properties": {},
            "slots": {},
            "type": "studio.core/rich-text",
            "version": "1.0.0"
          },
          {
            "authoring": { "mode": "structural" },
            "bindings": {},
            "id": "welcome-grid",
            "properties": { "columns": 1 },
            "responsive": { "columns": { "medium": 2 } },
            "slots": {
              "items": [
                {
                  "authoring": { "mode": "content" },
                  "bindings": {
                    "title": { "source": { "kind": "static-value", "value": "The wire" } },
                    "summary": {
                      "source": {
                        "kind": "static-value",
                        "value": {
                          "type": "doc",
                          "content": [
                            {
                              "type": "paragraph",
                              "content": [
                                {
                                  "type": "text",
                                  "text": "POST a Studio envelope to /port/artifact/load and the dispatcher answers with canonical bytes."
                                }
                              ]
                            }
                          ]
                        }
                      }
                    }
                  },
                  "id": "welcome-card-wire",
                  "properties": {},
                  "slots": { "actions": [] },
                  "type": "studio.core/card",
                  "version": "1.0.0"
                },
                {
                  "authoring": { "mode": "content" },
                  "bindings": {
                    "title": { "source": { "kind": "static-value", "value": "The render" } },
                    "summary": {
                      "source": {
                        "kind": "static-value",
                        "value": {
                          "type": "doc",
                          "content": [
                            {
                              "type": "paragraph",
                              "content": [
                                {
                                  "type": "text",
                                  "text": "The same library turned this composition into the page you are reading, and the markup works with no JavaScript at all."
                                }
                              ]
                            }
                          ]
                        }
                      }
                    }
                  },
                  "id": "welcome-card-render",
                  "properties": {},
                  "slots": { "actions": [] },
                  "type": "studio.core/card",
                  "version": "1.0.0"
                }
              ]
            },
            "type": "studio.core/grid",
            "version": "1.0.0"
          },
          {
            "authoring": { "mode": "content" },
            "bindings": {
              "title": { "source": { "kind": "static-value", "value": "Demonstration only" } },
              "content": {
                "source": {
                  "kind": "static-value",
                  "value": {
                    "type": "doc",
                    "content": [
                      {
                        "type": "paragraph",
                        "content": [
                          {
                            "type": "text",
                            "text": "Everything lives in memory and resets on restart; nothing here is a production host."
                          }
                        ]
                      }
                    ]
                  }
                }
              }
            },
            "id": "welcome-notice",
            "properties": { "dismissible": true, "tone": "information" },
            "slots": {},
            "type": "studio.core/notice",
            "version": "1.0.0"
          },
          {
            "authoring": { "mode": "content" },
            "bindings": {},
            "id": "welcome-guestbook",
            "properties": {},
            "slots": {},
            "type": "example.blocks/guestbook",
            "version": "1.0.0"
          }
        ]
      },
      "type": "studio.core/section",
      "version": "1.0.0"
    }
  ]
}
JSON;

    private function __construct()
    {
    }

    /**
     * The stored composition, freshly decoded so callers can never mutate a
     * shared instance.
     */
    public static function composition(): \stdClass
    {
        $document = CanonicalJson::decode(self::COMPOSITION_JSON);
        assert($document instanceof \stdClass);

        return $document;
    }

    /**
     * The demonstration theme: tokens the base stylesheet vocabulary
     * actually consumes.
     *
     * @return array<string, string>
     */
    public static function themeTokens(): array
    {
        return [
            'inverse-background' => '#1c1b1f',
            'inverse-foreground' => '#f5f2ea',
            'space' => '1.25rem',
        ];
    }
}

/**
 * Allow exactly the demonstration actor; refuse everyone else, per call.
 *
 * The actor arrives from the trusted transport (the front controller reads
 * an HTTP header), never from the envelope — exactly the division the wire
 * layer documents.
 */
final class DemoAuthorization implements AuthorizationInterface
{
    public function __construct(private readonly ?string $actor)
    {
    }

    public function authorize(Operation $operation, RequestEnvelope $request): ?HostError
    {
        if ($this->actor === null) {
            return HostError::unauthenticated(new MessageReference(
                'examples.minimal-host/no-actor',
                'Send the x-demo-actor header to identify yourself to the demonstration host.'
            ));
        }
        if ($this->actor !== MinimalHost::DEMO_ACTOR) {
            return HostError::forbidden(new MessageReference(
                'examples.minimal-host/actor-forbidden',
                'The demonstration host allows only its demonstration actor.'
            ));
        }

        return null;
    }
}

/**
 * The atomic mutation contract reduced to one request-lifetime array —
 * enough to make keyed replay semantics observable in a single process,
 * durable for exactly as long as a demonstration needs. Production hosts
 * include mutation and audit in one transaction and protect stored replay
 * material; this deliberately tiny example has no database or secrets.
 */
final class DemoMutationBoundary implements MutationBoundaryInterface
{
    /** @var array<string, MutationOutcome> */
    private array $records = [];

    public function execute(
        Operation $operation,
        RequestEnvelope $request,
        ?string $scopeKey,
        ?string $intentDigest,
        callable $mutation,
    ): MutationOutcome {
        if ($scopeKey !== null && isset($this->records[$scopeKey])) {
            return $this->records[$scopeKey];
        }
        $outcome = new MutationOutcome($intentDigest, $mutation());
        if ($scopeKey === null) {
            return $outcome;
        }

        return $this->records[$scopeKey] = $outcome;
    }
}

/**
 * The required artifact port over one in-memory artifact.
 *
 * Load returns the fixture composition at the current revision; the
 * concurrency-protected mutations enforce expected-revision writes with the
 * canonical conflict (carrying the safe current revision) and advance the
 * revision deterministically. No clock, no randomness, no storage.
 */
final class DemoArtifactPort implements ArtifactPortInterface
{
    private int $generation = 1;

    private ?\stdClass $document = null;

    public function dependencies(mixed $arguments, RequestContext $context): HostResult
    {
        return new HostResult([]);
    }

    public function load(mixed $arguments, RequestContext $context): HostResult
    {
        $requested = $arguments instanceof \stdClass && is_string($arguments->id ?? null)
            ? $arguments->id
            : DemoFixtures::ARTIFACT_ID;
        if ($requested !== DemoFixtures::ARTIFACT_ID) {
            throw new HostRefusal(HostError::notFound(new MessageReference(
                'examples.minimal-host/artifact-unknown',
                'The demonstration host stores exactly one artifact: examples/welcome.'
            )));
        }
        $document = $this->document ?? DemoFixtures::composition();
        $document->revision = $this->revision();

        return new HostResult($document);
    }

    public function publish(mixed $arguments, RequestContext $context): HostResult
    {
        return new HostResult(null, $this->advance($context));
    }

    public function save(mixed $arguments, RequestContext $context): HostResult
    {
        $revision = $this->advance($context);
        if ($arguments instanceof \stdClass) {
            $this->document = $arguments;
        }

        return new HostResult(null, $revision);
    }

    public function unpublish(mixed $arguments, RequestContext $context): HostResult
    {
        return new HostResult(null, $this->advance($context));
    }

    private function revision(): string
    {
        return DemoFixtures::ARTIFACT_ID . '-r' . $this->generation;
    }

    private function advance(RequestContext $context): string
    {
        if ($context->expectedRevision !== $this->revision()) {
            throw new HostRefusal(HostError::conflict(
                new MessageReference(
                    'examples.minimal-host/artifact-conflict',
                    'Another revision was accepted first; resolve against the returned revision.'
                ),
                $this->revision()
            ));
        }
        $this->generation++;

        return $this->revision();
    }
}

/**
 * Everything the dispatcher needs, wired from the fixtures above. The
 * optional ports return null, so requests addressed to them demonstrate the
 * canonical unavailable refusal.
 */
final class MinimalHost implements HostAdapterInterface
{
    public const DEMO_ACTOR = 'demo-editor';

    private readonly DemoAuthorization $authorization;

    private readonly DemoMutationBoundary $mutations;

    private readonly DemoArtifactPort $artifact;

    public function __construct(?string $actor)
    {
        $this->authorization = new DemoAuthorization($actor);
        $this->mutations = new DemoMutationBoundary();
        $this->artifact = new DemoArtifactPort();
    }

    public function authorization(): AuthorizationInterface
    {
        return $this->authorization;
    }

    public function mutations(): MutationBoundaryInterface
    {
        return $this->mutations;
    }

    public function artifact(): ArtifactPortInterface
    {
        return $this->artifact;
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

/**
 * The demonstration render path: the fixture composition through the real
 * composition renderer, and the page stylesheet as the theme's compiled
 * tokens ahead of the render result's CSS — the exact recipe the host guide
 * documents.
 */
final class DemoPage
{
    private function __construct()
    {
    }

    public static function render(): RenderResult
    {
        return (new CompositionRenderer())->renderDocument(DemoFixtures::composition());
    }

    public static function stylesheet(RenderResult $result): string
    {
        return ThemeStylesheet::compile(DemoFixtures::themeTokens()) . "\n" . $result->css;
    }
}
