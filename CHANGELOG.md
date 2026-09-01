# Changelog

Delivered, repository-verified behaviour only; roadmap position and claims live in
[`docs/roadmap.md`](docs/roadmap.md).

## 0.2.0 - 2026-09-01

- Re-pinned Producer to the provenance-backed Studio `0.1.0-beta.3` publication at exact source
  commit `42b149251a9f17a2ef8f32db0d9dd1ac2fcfec8a`: byte-identical release records, all 55 protocol
  schemas, all 301 testkit corpus members, manifest digests, package coordinates, protocol version,
  and the deliberately empty conformance-profile claim are now one fail-closed contract proof.
- Bound all eight coordinated Studio npm packages to their exact tarball sizes, SHA-1 shasums,
  SHA-256 hashes, SHA-512 integrity values, and npm provenance attestations. Added typed access to
  the Studio asset manifest and its exact browser-module and enhancement-runtime bytes, with
  package paths, byte budgets, content hashes, and SRI values verified end to end. The package now
  also preserves and independently verifies all fourteen manifest-declared Studio and third-party
  redistribution notice/license files.
- Verified the exact non-vendored Studio browser archive and detached checksum against the
  governed GitHub prerelease downloads: deterministic regular-file ustar, 74 members, fixed byte
  budget, SHA-256, SHA-512/SRI, inner-manifest digest, and byte equality with every
  manifest-declared npm member. The PIN binds the published envelope and its live governed URLs
  without shipping the 1.4 MB release assets in Producer's Composer archive.
- Hardened the deterministic importer so Studio release, schema, corpus-manifest, and member bytes
  come only from bounded ordinary blobs in the exact evidence commit, never a dirty checkout. Git
  plumbing uses a canonical executable, sanitized configuration environment, and disabled replace
  objects so `refs/replace` cannot substitute the evidence tree.
  Canonical inputs reject final and ancestor links; regular-file reads preflight identity and size;
  npm packages use a bounded streaming gzip/type-0-ustar reader that rejects links, special members,
  oversized inflation, and compressed bombs before retaining member bytes. Contract replacement
  now has checked rollback, explicit recovery state when rollback itself fails, and post-commit
  backup cleanup that cannot misreport a successfully installed generation as failed.
- Restored the seven released authoring operations, their schemas and fixtures, and the optional
  authoring port interface. The wire now reproduces the pinned Studio release's
  thirty-one-operation, ten-port registry exactly, with no alternate host-specific paths.
- Cleared the recorded release blocker: the governed Studio GitHub prerelease
  `studio-v0.1.0-beta.3` publishes both exact outer-archive assets, the deterministic importer
  re-ran against those public downloads, and the pin's release-readiness state is `ready` with no
  blockers. The importer and installed-runtime verification now require the published envelope —
  live governed tag, release, archive, and checksum URLs — instead of a blocked local candidate.
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
- Restricted Composer archives to the runtime library, public API manifest, exact Studio contract,
  license, readme, and a shipped no-dev smoke; development and generated state never crosses the
  distribution boundary.
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
