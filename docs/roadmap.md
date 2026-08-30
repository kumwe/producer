# Producer roadmap

Forward work only; delivered work moves to [`CHANGELOG.md`](../CHANGELOG.md) in the change that
completes it. A step is claimed only when `php tools/check.php` proves it on a clean clone.

## Position

Runtime realization. The current unreleased integration target includes the pinned contract lane
(P-1), the canonical serializer (P-2), the sealed exact document-schema registry, the wire layer
with the closed thirty-one-operation registry and ten operational port interfaces, including
authoring (P-3 and the interface half of P-9), the schema-property evaluator, the complete
forty-five-type renderer with its escaping discipline and fallbacks (P-4, P-5), the stylesheet
generator (P-6), rich-text projection rendering (P-7), and the enhancement need signal on the
render result (the Producer half of P-8).

Producer is pinned to the provenance-backed eight-package Studio `0.1.0-beta.2` npm publication at
source commit `38a96472ff4a5e1aa1fb92ed5451dc0fd112cf48`, including all 55 protocol schemas and all 301
testkit corpus members. The release claims zero Studio conformance profiles. The Studio asset
manifest, browser module, and enhancement runtime are package-path- and digest-verified, but the
governed publication has no approved outer Studio browser archive or detached archive checksum.
That missing artifact blocks a Producer release; the npm packages and their inner assets do not
replace it. Host integration may claim only the delivered surfaces above; P-10, P-11, P-12, and
release readiness remain open.

## Steps

| # | Step | Proof |
| --- | --- | --- |
| P-1 | Vendored, digest-verified Studio contract: exact source commit, eight npm packages, 55 schemas, 301 corpus members, and manifest-bound browser assets in `PIN.json` | `php tools/verify-contract.php` |
| P-2 | Canonical JSON encoder byte-identical to Studio's serialization | replay of the canonical vector corpus |
| P-3 | Wire layer: envelope validation, exact released thirty-one-operation/ten-port registry including authoring, strict responder, twelve-category error taxonomy, host-atomic mutation/replay boundary | wire suite + released host fixtures |
| P-4 | Renderer engine: exact published coordinates, closed preview markers, tri-state bindings, escaping, draft fallback | renderer unit suite |
| P-5 | The complete Studio block catalog rendered with no-JavaScript fallbacks | replay of the renderer-web conformance vectors |
| P-6 | Theme design tokens and layout vocabulary → generated static stylesheet | `cssContains` vector assertions + css suite |
| P-7 | Rich-text projection rendering through the canonical grammar | rich-text conformance corpus replay |
| P-8 | Browser-artifact handshake: data-attribute requests, runtime requirement flag, and manifest-verified browser module and enhancement runtime; approved outer Studio browser archive still required for release | vector `enhancements` assertions + browser-asset proof |
| P-9 | Host port interfaces + in-memory reference host + host-vector replay | host conformance suite |
| P-10 | Deployment emitter and security verifiers, adopted from the Studio reference at the first published-release re-pin | PHP boundary suite |
| P-11 | Twig bridge: embed the render result (fragment, stylesheet reference, enhancement flag, preload hints) as a thin extension | bridge suite |
| P-12 | Resolve the approved outer Studio browser archive/checksum blocker, then publish to Packagist with the host adoption guide | release-readiness gate + clean-room install proof |

Deferred by design: anything the charter forbids, and any shape outside the published pin —
Producer implements the exact provenance-backed Studio contract only.
