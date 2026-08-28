# Producer roadmap

Forward work only; delivered work moves to [`CHANGELOG.md`](../CHANGELOG.md) in the change that
completes it. A step is claimed only when `composer check` proves it on a clean clone.

## Position

Founding. The Studio pin is a pre-release git source pin; it moves to the first coordinated
published release when Studio ships it, and every consumer-facing claim waits for that re-pin.

## Steps

| # | Step | Proof |
| --- | --- | --- |
| P-1 | Vendored, digest-verified Studio contract (schemas + conformance corpus) with `PIN.json` | `composer contract` |
| P-2 | Canonical JSON encoder byte-identical to Studio's serialization | replay of the canonical vector corpus |
| P-3 | Wire layer: envelope validation, closed operation registry incl. the seven authoring operations, strict responder, twelve-category error taxonomy | wire test suite + negative fixtures |
| P-4 | Renderer engine: registry, dispatch, escaping discipline, unresolved-block semantic fallback | renderer unit suite |
| P-5 | The complete Studio block catalog rendered with no-JavaScript fallbacks | replay of the renderer-web conformance vectors |
| P-6 | Theme design tokens and layout vocabulary → generated static stylesheet | `cssContains` vector assertions + css suite |
| P-7 | Rich-text projection rendering through the canonical grammar | rich-text conformance corpus replay |
| P-8 | Enhancement-runtime handshake: data-attribute requests, runtime requirement flag, pinned asset reference | vector `enhancements` assertions |
| P-9 | Host port interfaces + in-memory reference host + host-vector replay | host conformance suite |
| P-10 | Deployment emitter and security verifiers, adopted from the Studio reference at the first published-release re-pin | PHP boundary suite |
| P-11 | Twig bridge: embed the render result (fragment, stylesheet reference, enhancement flag, preload hints) as a thin extension | bridge suite |
| P-12 | Packagist publication and the host adoption guide | clean-room install proof |

Deferred by design: anything the charter forbids, and any shape that exists only in unmerged
Studio work — Producer implements merged, pinned contract only.
