# Host guide

This is the adoption path for a PHP application that wants Studio compositions on its pages. It
assumes no prior context: not with Studio, not with this repository. The rules it rests on are the
[charter](../CHARTER.md) and the [host agreement](host-agreement.md); this guide is the practical
walk from `composer require` to a served page. A complete, runnable demonstration of everything
below lives in [`examples/minimal-host/`](../examples/minimal-host/README.md).

## The five-minute picture

**Studio designs it. Producer makes it real. Your application — the host — owns it.**

[Kumwe Studio](https://github.com/kumwe/studio) is a browser application where an author composes
typed, theme-bounded blocks. It hands your application canonical JSON — never markup, never
styles, never code. Producer is the PHP library that makes that JSON real on your server, and it
deliberately holds no authority of its own: no authentication, no database, no filesystem, no
network. Everything effectful crosses a small PHP interface that you implement.

Adopting Producer means three things:

- **You implement the port interfaces.** Producer's wire layer receives Studio's requests, proves
  them contract-shaped, and then hands every decision to your code through
  `Kumwe\Producer\Wire\Port\HostAdapterInterface`. Two members of that adapter are always
  consulted and always required: `AuthorizationInterface` (your allow/refuse decision, asked
  first, for every operation) and `IdempotencyLedgerInterface` (your durable storage for mutation
  replay detection). The artifact port (`ArtifactPortInterface`, your versioned persistence) is
  the one required operation port; the other nine ports — authoring, localization, media, model,
  permission, preview, recovery, resource, telemetry — are optional, and a request addressed to a
  port you return `null` for is refused as `unavailable`, never guessed at.
- **You embed the render result.** Rendering a published composition yields a
  `Kumwe\Producer\Render\RenderResult` with three members: `html` (a semantic, fully escaped
  fragment), `css` (static stylesheet text), and `enhancements` (the list of progressive-behavior
  requests, with `enhancementNames()` as its deduplicated summary). Producer never owns a page:
  your layout, navigation, language, and direction wrap the fragment, and your pipeline delivers
  the CSS — Producer never emits a `<style>` or `<script>` element.
- **You serve Studio's prebuilt assets.** The authoring application and the public enhancement
  runtime are prebuilt, versioned Studio release artifacts. You serve them as immutable static
  files under your own cache and Content-Security-Policy discipline; Producer pins which release
  they come from and never compiles, bundles, or generates JavaScript.

## Wiring the wire

Studio's client speaks a closed set of thirty-one operations, each addressed to a transport route
such as `artifact/load` or `media/list` (`Kumwe\Producer\Wire\OperationRegistry` holds the full
table). The host exposes **one HTTP endpoint** that receives those requests and drives each one
through `Kumwe\Producer\Wire\Dispatcher`:

1. The dispatcher resolves the route in the closed registry — an unknown route is a typed
   `invalid-request` refusal, never a passthrough.
2. `RequestEnvelope::parse()` proves the body is the pinned contract's request shape: size-bounded
   (1 MiB by default), valid UTF-8, well-formed JSON with duplicate members refused, envelope
   members matching the contract grammars, a supported wire version, a registered operation.
3. **Authorization comes first.** Your `AuthorizationInterface::authorize()` receives the resolved
   `Operation` and the full validated `RequestEnvelope` (the argument is part of the decision —
   authorization is item-scoped) and answers per call: `null` allows exactly this call, a
   `HostError` refuses it and is emitted verbatim. Producer never caches an allow, and if your
   implementation throws, the request fails closed as `internal`. Nothing later runs after a
   refusal.
4. For a mutation carrying an idempotency key, your ledger is consulted; a matching record replays
   the stored outcome without touching the port, and a key reused with changed intent is refused.
5. The operation reaches your port implementation, which returns a
   `Kumwe\Producer\Wire\HostResult` or throws `Kumwe\Producer\Error\HostRefusal`.
6. `StrictResponder` turns the outcome into a `Kumwe\Producer\Wire\Response`: canonical JSON
   bytes plus a header list (`content-type: application/json`, `cache-control: no-store`,
   `x-content-type-options: nosniff`, `content-length`).

All of that is one call. The endpoint, framework-neutral:

```php
<?php

declare(strict_types=1);

use Kumwe\Producer\Wire\Dispatcher;

// Your framework routes e.g. POST /studio/port/{route} here, where {route}
// is the registry route ("artifact/load"). Authentication happened earlier,
// in your own middleware: the envelope never carries identity — the trusted
// transport attaches it, and your adapter carries it to your authorization.
$route = substr($path, strlen('/studio/port/'));
$body = (string) file_get_contents('php://input');

$dispatcher = new Dispatcher(new YourHostAdapter($authenticatedActor));
$response = $dispatcher->dispatch($route, $body);

foreach ($response->headers as $name => $value) {
    header($name . ': ' . $value);
}
http_response_code(200); // outcomes are distinguished by body shape, never by status
echo $response->body;
```

`YourHostAdapter` is your implementation of `HostAdapterInterface` — the runnable
[minimal host](../examples/minimal-host/README.md) shows a complete in-memory one. Two dispatcher
knobs exist and both default sensibly: a `StrictResponder` instance and `maximumBodyBytes`
(default `RequestEnvelope::DEFAULT_MAXIMUM_BODY_BYTES`, 1 MiB; raise it explicitly if your
artifacts are larger).

`Response` carries no HTTP status on purpose: the contract distinguishes a success (a host-result
document) from a refusal (a host-error document) by body shape alone. The `refusal` flag tells
your transport which shape it is holding, for your own logging or status policy.

### What each refusal means to an operator

Every refusal is a canonical host-error document: a `category` from the closed twelve-category
taxonomy, a `message` that is a catalog key plus a bounded pre-written fallback (never echoed
request data, never host internals), a `retryable` flag, and bounded structured `diagnostics`.
Match on the category and the stable message key — never on message text.

| Category | What it tells an operator |
| --- | --- |
| `invalid-request` | The request breaks the contract: malformed envelope, unknown route or operation, a misused member, a key reused with changed intent. Fix the caller; retrying changes nothing. |
| `unauthenticated` | Your authorization found no usable identity on the transport. |
| `forbidden` | Your authorization refused this actor this operation on this item. |
| `not-found` | Your port could not find the addressed resource. |
| `conflict` | An `expectedRevision` no longer matches. `revision` carries the safe current revision to rebase on without a second read. |
| `validation-failed` | The argument fit the wire shape but your port refused its semantics. |
| `incompatible` | The envelope names a wire `protocolVersion` this pin does not speak; the pins have drifted. Fix the pins, not the request. |
| `limit-exceeded` | The body exceeded the configured byte bound. |
| `rate-limited` | Your throttling refused the request. Always retryable; may carry `retryAfterMilliseconds`. |
| `unavailable` | A port this host does not provide (`retryable` false), or your outage (`retryable` only when you declare it transient). |
| `cancelled` | The host recorded this operation as cancelled. |
| `internal` | Fail-closed mapping of any unclassified host fault. The body discloses nothing; correlate with your own logs. |

Refusals Producer raises itself carry `kumwe.producer/…` message keys (for example
`kumwe.producer/unknown-operation`, `kumwe.producer/protocol-version-unsupported`,
`kumwe.producer/idempotent-intent-changed`); refusals your host raises carry your keys and pass
through verbatim.

## Rendering published pages

A published composition is canonical JSON your application stored through the artifact port.
Realizing it is a pure, deterministic transformation — same composition, same theme, same Producer
release, identical output bytes:

```php
<?php

declare(strict_types=1);

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Render\CompositionRenderer;
use Kumwe\Producer\Render\RenderContext;

$document = CanonicalJson::decode($storedCompositionJson); // keeps {} and [] distinct
$renderer = new CompositionRenderer();                     // complete pinned core catalog
$result = $renderer->renderDocument($document, new RenderContext());

$result->html;               // semantic, fully escaped fragment — embed in your template
$result->css;                // static stylesheet text — deliver through your pipeline
$result->enhancementNames(); // e.g. ['notice', 'chart'] — the runtime need signal
```

Always decode with `CanonicalJson::decode()`: it preserves the `{}`-versus-`[]` distinction the
renderer relies on (objects decode to `stdClass`, arrays to lists).

`RenderContext` is where the host injects rendering authority, and every member fails closed when
absent:

- `resolveBinding` — a `callable(\stdClass $node, string $port): mixed` resolving a node's port
  value from your data. Without it, only `static-value` bindings stored on the node resolve.
- `resolveMedia` — a `callable(\stdClass $reference): ?ResolvedMedia` resolving a media reference
  through your media service. Without it, every media block renders its unavailable fallback.
  Whatever URL you resolve is still vetted against the closed allowlist before it reaches a page.
- `allowBlobMedia` — default `false`; only an explicit `true` lets vetted `blob:` URLs through.
- `scopedStyles` — per-node structured style intent, keyed by node id, compiled through the closed
  scoped-CSS vocabulary (`Kumwe\Producer\Css\ScopedStylesheet::compile()`).

A block type without a registered renderer is not an error: the page renders a bounded, labeled
semantic fallback (`Unsupported Studio block …`) and everything else still works. A host with its
own block types registers additional renderers on a `BlockRendererRegistry` and passes it to the
`CompositionRenderer` constructor.

### Embedding with Twig

Producer ships **no** template engine and no Twig dependency — the charter forbids runtime
dependencies. If your application already uses Twig, the bridge is a ~15-line extension you copy
into your own codebase, where Twig remains your dependency:

```php
<?php

declare(strict_types=1);

use Kumwe\Producer\Canonical\CanonicalJson;
use Kumwe\Producer\Render\CompositionRenderer;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

final class StudioCompositionExtension extends AbstractExtension
{
    public function __construct(private readonly CompositionRenderer $renderer)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('studio_composition', function (string $json): array {
            $result = $this->renderer->renderDocument(CanonicalJson::decode($json));

            return [
                'html' => new Markup($result->html, 'UTF-8'),
                'css' => $result->css,
                'needs_runtime' => $result->enhancementNames() !== [],
            ];
        })];
    }
}
```

`Markup` marks the fragment as already-escaped for Twig's autoescaping — which is true: every
stored value passed through Producer's single escaping path. A template then embeds it inside your
own layout:

```twig
{% set page = studio_composition(composition_json) %}
<link rel="stylesheet" href="{{ composition_css_url }}">
{{ page.html }}
{% if page.needs_runtime %}
  <script src="{{ studio_runtime_url }}" defer></script>
{% endif %}
```

The same shape works in any engine — the extension above is ordinary host code calling the same
three-member result.

### Caching the stylesheet

Two static pieces make up a page's CSS, and neither needs per-request computation:

- **Per theme:** `Kumwe\Producer\Css\ThemeStylesheet::compile($tokens)` turns a theme's design
  tokens into a sorted `--studio-*` custom-property block, and
  `ThemeStylesheet::document($tokens)` appends the static base stylesheet (the layout vocabulary
  and the `prefers-reduced-motion` overrides from `BaseStylesheet::css()`). Build it once when a
  theme is published and cache it under the theme's revision.
- **Per composition:** `RenderResult->css` carries the base stylesheet plus the per-node scoped
  rules this composition implies (responsive column variables, cover overlays, host style
  intent). It is deterministic, so cache it keyed by the composition revision and the Producer
  release, and serve it as a static file — never inline it.

### The enhancement runtime signal

Published HTML works without JavaScript; that is a promise of the host agreement. Progressive
behavior (dismissable notices, tab activation, charts, slideshows) comes only from **Studio's
prebuilt, versioned enhancement runtime**, which blocks request through `data-*` attributes —
Producer never generates a script. `RenderResult->enhancements` records every request in document
order, and the include decision is one comparison:

```php
$needsRuntime = $result->enhancementNames() !== [];
```

When the list is empty, the page includes no script at all. When it is not, your template
references the runtime file you serve from your own static asset mount (see the CSP section
below). Which exact file that is comes from the Studio release named by the pin — you serve it,
you never build it.

## The security posture the host must keep

Producer's output is safe by construction; the host's job is to serve it without undoing that.

### Content-Security-Policy

The rendered fragment contains no inline script, no inline style attribute or element, and no
event-handler attributes — ever. URLs bound into pages pass a closed allowlist (site-relative
paths, fragments, and `https:` origins; `blob:` only under your explicit `allowBlobMedia`
authority, and never for SVG/HTML media types). So a published page holds under a strict policy
with no `unsafe-inline` anywhere:

```
Content-Security-Policy: default-src 'none'; base-uri 'none'; form-action 'self';
  frame-ancestors 'self'; img-src 'self' https:; media-src 'self' https:;
  script-src 'self'; style-src 'self'
```

- `style-src 'self'` works because you deliver the generated CSS as a file (or from your own
  nonce'd element — your pipeline, your choice). Producer never asks for inline style.
- `script-src 'self'` covers the one script a page may reference: Studio's prebuilt enhancement
  runtime, served from your own origin. Pages whose enhancement list is empty need no script
  source at all.
- Add `blob:` to `img-src`/`media-src` only if you deliberately enable `allowBlobMedia`.
- If your compositions bind media from a known CDN, narrow `https:` to that origin.

The **authoring mount** — the path where you serve Studio's prebuilt authoring application to your
editors — is a separate document with its own policy. Serve it as immutable, versioned static
files on its own route, behind your authentication, with a CSP scoped to that route; its exact
directives follow the Studio release you pin, not this guide. Keep the published-page policy
strict and separate: an editor capability must never widen what a public page may do.

### The never-list

Producer guarantees that **no stored markup, no stored style, and no stored script ever reaches
output**: every stored string crosses one escaping path, rich text renders only through the
validating portable grammar, structured styles compile only through a closed property and value
vocabulary, and raw HTML strings have no representation at all. The host must not undo this:

- Never post-process the rendered fragment — no HTML "sanitizer" that re-parses and re-serializes,
  no entity-decoding filter, no template `|raw`-style interpolation of stored values *next to* the
  fragment. The fragment is finished; wrapping it is embedding, rewriting it is a defect.
- Never rebuild the CSS from strings a user stored. Theme tokens and scoped styles go through
  Producer's compilers, which enforce the closed grammar; concatenating stored CSS text around
  them reintroduces exactly what the grammar removed.
- Never inject a script into the fragment, and never inline the enhancement runtime. Reference it
  as the static, versioned file from the pinned Studio release.
- Never serve wire responses under a widened content-type or without the headers `Response`
  carries: they exist so port responses (which can hold private resource data) are never sniffed
  or cached.

## The pin

Alignment across the three parties is exact, never floating:

- **Studio → Producer:** Producer vendors the Studio contract corpus (schemas plus conformance
  vectors) at one exact release, recorded with per-file SHA-256 digests in
  [`resources/studio-contract/PIN.json`](../resources/studio-contract/PIN.json).
  `composer contract` fails when a vendored byte disagrees with a recorded digest, so the
  contract your host runs against is provably the pinned one.
- **Producer → host:** pin the exact Producer version in your `composer.json` — an exact version
  string, never a range (`^`/`~`) — while Studio's contract is pre-release, and record the
  Producer and Studio pins you qualified in your own release evidence. Producer versions
  independently; Studio alignment travels only through `PIN.json`.
- **Re-pinning:** a contract change reaches you only as Studio release → Producer re-pin (one
  reviewed change) → Producer release → your pin bump. No stage is skipped and nothing floats;
  the protocol is recorded in the [host agreement](host-agreement.md). A wire-version mismatch at
  runtime surfaces as the `incompatible` refusal above — the remedy is always a pin correction.

Producer runs on PHP 8.1 or newer with `ext-json` and `ext-mbstring`, and adds no runtime Composer
dependencies to your tree.

## The runnable example

[`examples/minimal-host/`](../examples/minimal-host/README.md) is a complete, dependency-free
demonstration host: an in-memory implementation of the port interfaces, one wire operation served
end-to-end, and the demo composition rendered to a full HTML page under the CSP above — runnable
with `php -S localhost:8080 -t examples/minimal-host/public` on a clean clone. It is a
demonstration, never a production host; its README says what to try and what each part shows.
