# Changelog

Delivered, repository-verified behaviour only; roadmap position and claims live in
[`docs/roadmap.md`](docs/roadmap.md).

## 0.2.0 - 2026-08-29

- Re-pinned Producer from an unreleased Studio branch commit to the exact coordinated Studio
  `0.1.0-rc.1` release consumed by Kumwe App: byte-identical release records, protocol schemas,
  complete testkit corpus, manifest digests, package coordinates, protocol version, and profile
  claims are now one fail-closed contract proof.
- Removed the seven post-release authoring operations, their three schemas, and the authoring port
  interface that were not part of Studio `0.1.0-rc.1`; the wire now reproduces the released
  twenty-four-operation, nine-port registry exactly, without aliases or compatibility paths.
- Replaced the split idempotency lookup/write seam with one host-atomic mutation boundary for
  keyed and unkeyed mutations. Hosts can commit trusted scope, mutation, audit, and an optional
  protected replay representation together; replay may deterministically rehydrate secrets, and
  an explicitly safe failed lifecycle can commit and replay its typed refusal without repeating
  the mutation.
- Added typed refusal categories on responses, canonical HostError replay decoding, optional
  revisions on non-artifact conflicts, and the public UTF-16 member comparator needed by hosts.
- Made published rendering fail closed on exact host-bound type/version/revision coordinates;
  the core draft catalog no longer invents block revisions. Added closed preview-marker
  injection and exact inventory reconciliation plus available/hidden/unavailable binding results.
- Added clean Composer install and strict PSR-4 smoke proof, a reviewed public-API snapshot, a
  package/layer architecture gate, and PHP 8.5 to both pull-request and release matrices.
- Added a sealed, exact-corpus-backed document-schema registry for all thirteen published runtime
  document kinds, with closed local and cross-document references, bounded reviewed patterns,
  deterministic diagnostics, and hostile corpus proof. Added immutable typed release coordinates
  plus manifest-bound testkit resource access so consumers can delete schema/corpus mirrors.
- Hardened canonical admission to reject invalid UTF-8, non-JSON PHP shapes, excessive nesting,
  non-finite numbers, and values outside the interoperable ECMAScript safe-integer range; schema
  equality now keeps adjacent large integers exact, and package-resource reads are bounded,
  regular-file-only, identity-checked operations.

## 0.1.0 - 2026-08-28

- The wire layer: request envelopes against the contract grammars with real duplicate-member
  detection, the closed operation registry of thirty-one operations across ten ports proven
  byte-identical to the pinned registry document, the error taxonomy with non-echoing messages
  and category-bounded retry hints, and a dispatcher that hands authorization to the host first,
  replays idempotent outcomes without re-applying, and never discloses an internal.
- The renderer engine: all forty-five catalog block types with semantic no-JavaScript fallbacks,
  one escaping path, security fallbacks, a bounded unknown-type fallback, document-order
  enhancement requests, and deterministic bytes — proven by the eight renderer conformance
  vectors and the eight rich-text projection fixtures.
- Static deterministic stylesheets: the base vocabulary with reduced motion, scoped styles
  through a closed grammar with hard bounds, and theme tokens as sorted custom properties.
- The schema-property profile: eval-free admission and instance validation proven by all
  sixty-two conformance vectors, with adversarial reference fan-out defused by memoization.
- The canonical serializer: byte-identical cross-language JSON proven by the canonical corpus,
  including UTF-16 surrogate member ordering and both rejection vectors.
- Founding: charter, host agreement, engineering standard, porting guide, releasing policy,
  package skeleton, the digest-pinned Studio contract, and the check lane.
