# Releasing Producer

Producer versions independently under semantic versioning; alignment with Studio travels through
the pin, never through matching version numbers.

Releasing is merging. Every Kumwe PHP library delivers the same way:

1. Prove that the pinned Studio record is release-ready. Package provenance and manifest-verified
   browser assets are required but do not replace an approved outer Studio browser archive and its
   detached checksum; a blocked pin stays under `## Unreleased`.
2. Land the work on `main` with its `CHANGELOG.md` section for the next version — the heading
   `## X.Y.Z - date` is the release record.
3. The `Release on record` workflow runs on every push to `main`: it re-proves the complete check
   lane, reads the newest recorded version, and when that version has no tag yet it creates
   `vX.Y.Z` through the repository API and publishes the GitHub release naming the exact Studio
   pin it implements. Nobody pushes a tag by hand; a push that records no new version is a
   verification-only run.
4. Packagist follows tags through its GitHub integration — submit `kumwe/producer` once at
   packagist.org and every later release appears without a credential in this repository.

The current Studio `0.1.0-beta.2` integration at source commit
`38a96472ff4a5e1aa1fb92ed5451dc0fd112cf48` is intentionally unreleased. Its eight npm packages,
55 schemas, 301 corpus members, browser module, and enhancement runtime are provenance- or
manifest-verified, and it claims zero conformance profiles. It cannot become a Producer release
until an approved outer Studio browser archive and detached checksum are published and recorded in
the pin.

Version policy:

- **Patch** — behaviour fixes at the same Studio pin.
- **Minor** — new capability, or a Studio re-pin that stays wire-compatible for hosts.
- **Major** — a change a host must act on, including a Studio re-pin that moves the wire.
- While Studio's contract is pre-release, Producer stays `0.x` and hosts pin exactly.
