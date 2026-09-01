# Releasing Producer

Producer versions independently under semantic versioning; alignment with Studio travels through
the pin, never through matching version numbers.

Releasing is merging. Every Kumwe PHP library delivers the same way:

1. Prove that the pinned Studio record is release-ready. Package provenance, manifest-verified
   browser assets, and a locally reproduced outer-archive candidate are required but do not replace
   the two exact governed GitHub release assets; a blocked pin stays under `## Unreleased`.
2. Land the work on `main` with its `CHANGELOG.md` section for the next version — the heading
   `## X.Y.Z - date` is the release record.
3. The `Release on record` workflow runs on every push to `main`: it re-proves the complete check
   lane, reads the newest recorded version, and when that version has no tag yet it creates
   `vX.Y.Z` through the repository API and publishes the GitHub release naming the exact Studio
   pin it implements. Nobody pushes a tag by hand; a push that records no new version is a
   verification-only run.
4. Packagist follows tags through its GitHub integration — submit `kumwe/producer` once at
   packagist.org and every later release appears without a credential in this repository.

The current Studio `0.1.0-beta.3` integration at source commit
`42b149251a9f17a2ef8f32db0d9dd1ac2fcfec8a` is release-ready. Its eight npm packages, 55 schemas,
301 corpus members, browser module, and enhancement runtime are provenance- or manifest-verified,
and it claims zero conformance profiles. Its deterministic 74-member browser archive and detached
checksum are published by the governed GitHub prerelease `studio-v0.1.0-beta.3`, and the pin was
regenerated from those public downloads.

The deterministic re-pin command requires all evidence-bearing inputs explicitly; it never fetches
or discovers a mutable coordinate:

```sh
php tools/import-studio-contract.php STUDIO_ROOT EVIDENCE_JSON \
    STUDIO_TGZ RENDERER_TGZ BROWSER_TAR BROWSER_SHA256
```

Every input path must be canonical and contain no symbolic-link component. The Studio checkout's
`HEAD` must equal the evidence commit, but controlled bytes are read directly from that commit's
ordinary Git blobs with replacement objects disabled and Git configuration inputs sanitized; dirty
tracked files and `refs/replace` cannot affect the import. npm gzip/tar input is streamed through
fixed compressed, inflated, member-count, member-size, type, path, and padding bounds.

Release readiness may record `ready` only when the importer runs against both files downloaded
from the exact governed GitHub prerelease URLs recorded in the evidence; a workflow-equivalent
local reproduction proves a candidate but never substitutes for those public downloads.

Version policy:

- **Patch** — behaviour fixes at the same Studio pin.
- **Minor** — new capability, or a Studio re-pin that stays wire-compatible for hosts.
- **Major** — a change a host must act on, including a Studio re-pin that moves the wire.
- While Studio's contract is pre-release, Producer stays `0.x` and hosts pin exactly.
