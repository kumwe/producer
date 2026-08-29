# Minimal host

A runnable, dependency-free demonstration of the adoption path in the
[host guide](../../docs/host-guide.md): the real library classes, an in-memory implementation of
the port interfaces, one wire operation served end-to-end, and the demonstration composition
rendered to a full HTML page under the guide's Content-Security-Policy.

**This is a demonstration, never a production host.** Identity is a plain HTTP header, the
mutation boundary is a request-lifetime PHP array, and the one stored artifact is a fixture that resets on every
restart. Its purpose is to make the host guide's claims executable and its shapes copyable — the
authority, durability, and storage a real host supplies are exactly the parts reduced to fixtures
here.

## Run it

From the repository root, with no composer install:

```sh
php -S localhost:8080 -t examples/minimal-host/public
```

## What to try

**The rendered page** — open <http://localhost:8080/>. The page is the fixture composition
(section, heading, rich text, a responsive two-column grid of cards, a dismissible notice) run
through `CompositionRenderer`, embedded in a small layout, with the generated stylesheet served as
`/page.css` (`ThemeStylesheet::compile()` for the theme tokens plus the render result's CSS). The
composition also contains one block type outside the pinned catalog, so the page shows the
bounded `Unsupported Studio block …` fallback instead of breaking. Because the notice is
dismissible the render result requests the `notice` enhancement, so the page conditionally
references Studio's prebuilt runtime file — which this repository does not vendor (it is a Studio
release artifact a real host serves itself), so the reference 404s harmlessly and the page keeps
working: that is the no-JavaScript guarantee, observable.

**The wire, accepted** — POST a Studio request envelope to the one port endpoint
(`0.1.0-draft.2` is `RequestEnvelope::WIRE_PROTOCOL_VERSION` at this pin):

```sh
curl -s http://localhost:8080/port/artifact/load \
  -H 'content-type: application/json' \
  -H 'x-demo-actor: demo-editor' \
  --data '{"arguments":{"id":"examples/welcome"},"context":{"operationId":"studio.operation/artifact.load","protocolVersion":"0.1.0-draft.2","requestId":"requests/demo-1","resourceContextKey":"contexts/demo","sessionGeneration":"session-1"}}'
```

The response is the canonical host-result document carrying the stored composition, with the
strict wire headers (`content-type: application/json`, `cache-control: no-store`,
`x-content-type-options: nosniff`).

**The wire, refused** — repeat the same request without the `x-demo-actor` header for the
canonical `unauthenticated` refusal, or with `-H 'x-demo-actor: intruder'` for `forbidden`. Both
are the host's own authorization answer, emitted verbatim by the dispatcher before any port runs.
Malformed bodies, unknown routes (`/port/artifact/delete`), and operations addressed to ports
this host does not provide (`/port/telemetry/emit`) each produce their own typed refusal.

## The pieces

| File | What it demonstrates |
| --- | --- |
| [`MinimalHost.php`](MinimalHost.php) | The single-file in-memory host: authorization, mutation boundary, artifact port, adapter, and the render recipe. |
| [`public/index.php`](public/index.php) | The front controller: the wire endpoint through `Dispatcher`, the page at `/`, the stylesheet at `/page.css`, the CSP header. |

The test suite proves the example truthful: `tests/Case/ExampleHostTest.php` replays the wire and
render paths above and runs with the rest of the suite via `php tests/run.php`.
