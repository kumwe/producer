# Porting guide: Producer in another language

Producer's job — receive Studio's canonical compositions, validate them, render them — is not a
PHP idea. This guide exists so that an implementer (human or agent) pointed at this repository can
produce `producer-py`, `producer-ts`, or any sibling quickly and correctly, with no other context.
When Kumwe's Flutter client needs composition realization, this is the map.

## What is normative, and what is merely PHP

**Normative — port the meaning exactly:**

- [`resources/studio-contract/`](../resources/studio-contract/) — the pinned Studio schemas and
  conformance corpora. These are language-neutral JSON. **The corpora are the specification**: a
  port is correct when it replays every vector with the same verdicts, bytes, codes, and pointers,
  and is incorrect otherwise, whatever its code looks like.
- The [charter](../CHARTER.md) prohibitions: no authority, no storage, no render-time code
  generation, no runtime dependencies. They apply in every language.
- The layer boundaries and their dependency direction
  ([engineering standard](engineering-standard.md)): Canonical, Schema, Error, Wire, Render, Css.
- The [host agreement](host-agreement.md): what a host implements, what Producer promises, the
  pin protocol.

**PHP-specific — do not port, translate the idea:**

- `stdClass` versus list arrays as the decoded value shape (that is PHP's way of keeping `{}` and
  `[]` distinct — your language will have its own; what matters is that the distinction survives
  decoding).
- The dependency-free test runner in [`tests/run.php`](../tests/run.php) — use your language's
  ordinary test harness, but keep the suite dependency-light and corpus-driven.
- `ext-mbstring` usage — any correct UTF-8 handling serves.

## Port in this order

Each layer is proven before the next begins, because each later layer consumes the earlier one.

1. **Canonical JSON** (`src/Canonical/`). Prove with
   `resources/studio-contract/testkit/vectors/canonical/` — all vectors, including both rejection vectors. Read the subtleties
   list below first; this layer is where ports fail.
2. **The schema-property profile** (`src/Schema/`). Prove with
   `resources/studio-contract/testkit/vectors/schema-profile/` —
   admission codes and schema pointers, instance verdicts with keyword and instance pointers,
   byte-budget and graph bounds enforced before expensive work.
3. **The error taxonomy and wire layer** (`src/Error/`, `src/Wire/`). Shapes come from
   `resources/studio-contract/protocol/schemas/host-error.schema.json`, `host-request`,
   `host-result`, `host-operations`. The
   operation registry is closed; port interfaces stay minimal and authority-free.
4. **Rendering** (`src/Render/`). Prove with
   `resources/studio-contract/testkit/conformance/renderer-web/` (contains/excludes/css/
   enhancements assertions) and `testkit/conformance/rich-text/`. Escaping is centralized; unknown blocks
   render the bounded semantic fallback; output is deterministic bytes.
5. **Stylesheets** (`src/Css/`). Design tokens to custom properties, the layout attribute
   vocabulary, the reduced-motion base — static, deterministically ordered output.

## The subtleties every port gets wrong once

Learn them here instead of in production:

1. **Member ordering is UTF-16 code-unit order, not byte order.** U+10000's lead surrogate
   (`D800`) sorts *before* U+FFFD, the reverse of UTF-8 byte comparison. Sorting object members
   with a byte comparator produces different canonical bytes and different digests for astral
   member names. See `CanonicalJson::compareCodeUnits` and the astral-name test.
2. **`{}` and `[]` must stay distinguishable after decoding.** A language whose JSON decoder maps
   both to the same structure (PHP associative arrays, some dynamic languages) must decode objects
   to a distinct type before canonicalizing.
3. **Negative zero canonicalizes to `0`.** And integer-valued doubles print as integers; the
   number grammar is ECMAScript's, not your runtime's default float formatting.
4. **String escaping is minimal ECMA-404**: quote, backslash, the five short control escapes, four
   *lowercase* hex digits for the remaining C0 range, and raw UTF-8 for everything else — no
   `\/`, no `\uXXXX` for non-ASCII.
5. **Depth is counted on containers** and refused with the stable code `depth-exceeded`; the
   prototype-polluting member names (`__proto__`, `prototype`, `constructor`) are refused with
   `forbidden-member` even where your language has no prototypes.
6. **Digests are SRI-style** (`sha256-` + base64) over the exact canonical UTF-8 bytes.
7. **Refusals match by stable code and pointer, never by message.** Diagnostic precedence in the
   schema profile is deterministic; replaying the corpus will catch an ordering deviation.
8. **Escaping and URL vetting are one path.** Ports that add a second, convenient string
   concatenation in one renderer reintroduce the exact class of defect the design removes.

## What a port must ship to claim conformance

1. The vendored, digest-verified contract at an exact Studio pin (its own `PIN.json`).
2. Green replay of every vendored corpus it implements, in its own test suite.
3. The charter's prohibitions restated in its own README, with the same force.
4. A check lane equivalent to `composer check`, run in CI on every supported runtime version.

A port that cannot yet do all four is welcome to exist — and states plainly which layers are
proven and which are not, exactly as this repository's [roadmap](roadmap.md) does.
