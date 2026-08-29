# Producer roadmap

Forward work only; delivered work moves to [`CHANGELOG.md`](../CHANGELOG.md) in the change that
completes it. A step is claimed only when `composer check` proves it on a clean clone.

## Position

Runtime realization. Delivered and proven on a clean clone: the pinned contract lane (P-1), the
canonical serializer (P-2), the sealed exact document-schema registry, the wire layer with the
closed twenty-four-operation registry and the port interfaces (P-3 and the interface half of
P-9), the schema-property profile, the complete
forty-five-type renderer with its escaping discipline and fallbacks (P-4, P-5), the stylesheet
generator (P-6), rich-text projection rendering (P-7), and the enhancement need signal on the
render result (the producer half of P-8). Producer is pinned to Studio's exact coordinated
`0.1.0-rc.1` release, including its protocol and complete testkit manifests. Host integration may
claim only the delivered surfaces above; P-10 and P-11 remain open.

## Steps

| # | Step | Proof |
| --- | --- | --- |
| P-1 | Vendored, digest-verified Studio contract (schemas + conformance corpus) with `PIN.json` | `composer contract` |
| P-2 | Canonical JSON encoder byte-identical to Studio's serialization | replay of the canonical vector corpus |
| P-3 | Wire layer: envelope validation, exact released twenty-four-operation registry, strict responder, twelve-category error taxonomy, host-atomic mutation/replay boundary | wire suite + released host fixtures |
| P-4 | Renderer engine: exact published coordinates, closed preview markers, tri-state bindings, escaping, draft fallback | renderer unit suite |
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
