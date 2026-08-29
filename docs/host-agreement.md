# The host agreement

This document records the contract between Producer and a host application, and between Producer
and Studio. Kumwe App is the first host; every clause is written so a second host needs no new
agreement.

## What the host implements

Producer defines small PHP interfaces for the authoritative concerns. The host implements them
with its own services and hands them to Producer at the boundary:

| Interface concern | Host supplies | Producer guarantees |
| --- | --- | --- |
| Authorization | The decision for every operation, re-checked per request, item-scoped | Fails closed on refusal; never caches an allow |
| Artifact storage | Versioned persistence with expected-revision writes | Hands over only canonical, schema-valid bytes |
| Mutation boundary | One transaction spanning mutation, audit, and optional trusted replay scope; protected outcome storage and rehydration | Deterministic replay identity, changed-intent refusal, and no prescribed plaintext storage shape |
| Media | Upload sessions and asset resolution through the host's media service | Policy-vector-conformant refusals |
| Localization | Message catalogues through the host's translation chain | Falls back rather than throwing on a missing key |

## What the host embeds

- Producer's render result — an HTML fragment, its generated stylesheet, and the enhancement
  need signal — inside the host's own templates. Producer never owns a
  page; the host's layout, navigation, language, and direction wrap the fragment.
- Studio's prebuilt browser assets (the authoring archive and the public enhancement runtime),
  served as immutable static files under the host's cache and Content-Security-Policy discipline.
  The host never compiles them.

## What Producer promises the host

- Every response and every rendered artifact validates against the pinned Studio schemas.
- Rendering is deterministic: same composition, same theme, same Producer release — identical
  output bytes.
- Published HTML works without JavaScript; enhancements are additive and come only from Studio's
  versioned runtime, requested through data attributes, never generated.
- Draft and preview rendering use bounded semantic fallback for unresolved blocks. Published
  rendering requires every node's exact type/version/revision lock to be bound by the trusted host
  and fails closed before returning markup when one is unavailable or ambiguous.
- No hidden I/O: Producer performs no network, filesystem, or database access of its own at
  runtime. Every effect flows through a host-implemented interface.
- Every mutation is released only after the host's atomic execution boundary commits its effect
  and audit. A keyed mutation also commits a protected replay representation; explicitly safe
  failed state may commit with its typed refusal, while every ordinary failure rolls back.

## The pin protocol

1. Studio publishes a coordinated release (one record naming every package version, the wire
   version, and the corpus digest).
2. Producer vendors that corpus under [`resources/studio-contract/`](../resources/studio-contract/)
   with [`PIN.json`](../resources/studio-contract/PIN.json) recording the exact source, and
   `composer contract` fails when a vendored byte disagrees with the recorded digests.
3. The host pins Producer exactly (no version ranges while Studio's contract is pre-release), and
   records the Producer and Studio pins it qualified in its own release evidence.
4. A Studio contract change reaches a host only as: Studio release → Producer re-pin (one change,
   its own review) → Producer release → host pin bump. No stage may be skipped; nothing floats.

## Raising a mismatch

A need the pinned contract cannot express is raised as an issue in the Studio repository and
recorded in Producer's roadmap; Producer never adds a proprietary extension to the wire, and a
host never paraphrases a canonical document to avoid a re-pin.
