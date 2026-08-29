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

## How it is built

The library is layered with one dependency direction — Canonical JSON, the schema-property
profile, the error taxonomy, the wire layer, rendering, stylesheets — each proven by replaying the
vendored Studio conformance corpora before the next layer consumes it. The
[engineering standard](docs/engineering-standard.md) states the architecture, code, testing, and
documentation rules in full: strict types, final classes, injected authority, centralized
escaping, deterministic output, typed refusals with stable codes, and a suite that proves intended
outcomes only — the conformance corpora are the spine, negative paths are first-class, and
frivolous tests are forbidden.

Producer is also written to be ported. The contract is language-neutral JSON, so a Python or
TypeScript sibling implements the same corpora and claims conformance the same way; the
[porting guide](docs/porting-guide.md) gives an implementer the order, the boundaries, and the
subtleties, so a port needs this repository and nothing else.

## Exact document admission

Hosts and extension tooling can validate decoded Studio documents through the one sealed,
release-pinned authority. The registry accepts only the thirteen published document kinds and
loads only Producer's digest-verified Composer resources; callers cannot inject schemas, roots,
references, patterns, directories, or compatibility aliases.

```php
use Kumwe\Producer\Schema\StudioDocumentSchemaRegistry;

$validation = StudioDocumentSchemaRegistry::fromVendoredCorpus()
    ->validate('blueprint', $decodedDocument);

if (!$validation->valid()) {
    foreach ($validation->diagnostics() as $diagnostic) {
        // $diagnostic->instancePath, ->keyword, ->message
    }
}
```

`StudioContractResources::releaseRecord()` exposes immutable typed release coordinates, while
`testkitBytes()` reads only digest-verified corpus-manifest members for consumer conformance
tests. Neither API exposes the package root or a generalized filesystem reader. Decoded inputs
must use the canonical JSON shape (`stdClass` objects and list arrays) and the interoperable
ECMAScript safe-integer range.

## Status

The 0.2 release line is the supported host-integration foundation for the exact coordinated Studio
`0.1.0-rc.1` release. Canonical JSON, exact document-schema admission, contributed schema-profile
validation, the twenty-four-operation wire, host-atomic mutation and protected replay, rendering,
rich text, and stylesheets are corpus-proven. Deployment emitters and optional template bridges
remain roadmap work and are not claimed. See
[`docs/roadmap.md`](docs/roadmap.md) for the precise boundary.

## Requirements

PHP 8.1 or newer with `ext-json` and `ext-mbstring`. No runtime Composer dependencies.

## Development

```sh
composer validate --strict
composer install --no-interaction
composer check   # lint, API/architecture gates, contract proof, suite
```
