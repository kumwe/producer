# Kumwe Producer

**Studio designs it. Producer makes it real. Your application owns it.**

Producer is the PHP realization layer for [Kumwe Studio](https://github.com/kumwe/studio), the
schema-aware visual composition platform. Studio hands your application a canonical, portable JSON
composition — never markup, never styles, never code. Producer is what turns that composition into
reality on a PHP host:

- **Wire handling** — parses and validates Studio's request envelopes, routes the closed operation
  set, and answers with strict canonical JSON and the stable twelve-category error taxonomy.
- **Rendering** — turns a published composition into semantic, fully escaped HTML with working
  no-JavaScript fallbacks for the complete Studio block catalog.
- **Stylesheets** — generates the static CSS a design's tokens and layout vocabulary imply; nothing
  is computed per request and nothing is inlined.
- **Contract proof** — vendors the Studio corpus at one exact pinned release, digest-verifies it,
  and replays the published conformance vectors, so what this library claims is what it proves.

Producer deliberately contains **no authority, no storage, no Node.js, and no render-time code
generation** — your application keeps authentication, authorization, persistence, and templates,
and implements Producer's small port interfaces with its own services. The full rules live in the
[charter](CHARTER.md); the division of labour with Studio and with host applications is recorded in
[`docs/host-agreement.md`](docs/host-agreement.md).

## The pipeline

| Stage | Owner | What happens |
| --- | --- | --- |
| Design | **Studio** (browser) | An author composes typed, theme-bounded blocks; Studio emits canonical JSON |
| Wire | **Producer** | Envelope validated, operation routed, host answers through its own ports |
| Authority and storage | **Your application** | Authorization, revisions, audit, persistence — all yours |
| Realization | **Producer** | Published composition → semantic HTML + generated stylesheet |
| Delivery | **Your application** | Embeds the render result in its own templates and serves Studio's prebuilt assets |

## Status

Founding stage. The contract is vendored from a pre-release Studio pin and the surface below is
under active construction; nothing here is a supported release yet. See
[`docs/roadmap.md`](docs/roadmap.md) for what exists, what is proven, and what is next.

## Requirements

PHP 8.1 or newer with `ext-json` and `ext-mbstring`. No runtime Composer dependencies.

## Development

```sh
composer check   # lint, contract digest verification, dependency-free test suite
```
