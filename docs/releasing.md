# Releasing Producer

Producer versions independently under semantic versioning; alignment with Studio travels through
the pin, never through matching version numbers.

Releasing is merging. Every Kumwe PHP library delivers the same way:

1. Land the work on `main` with its `CHANGELOG.md` section for the next version — the heading
   `## X.Y.Z - date` is the release record.
2. The `Release on record` workflow runs on every push to `main`: it re-proves the complete check
   lane, reads the newest recorded version, and when that version has no tag yet it creates
   `vX.Y.Z` through the repository API and publishes the GitHub release naming the exact Studio
   pin it implements. Nobody pushes a tag by hand; a push that records no new version is a
   verification-only run.
3. Packagist follows tags through its GitHub integration — submit `kumwe/producer` once at
   packagist.org and every later release appears without a credential in this repository.

Version policy:

- **Patch** — behaviour fixes at the same Studio pin.
- **Minor** — new capability, or a Studio re-pin that stays wire-compatible for hosts.
- **Major** — a change a host must act on, including a Studio re-pin that moves the wire.
- While Studio's contract is pre-release, Producer stays `0.x` and hosts pin exactly.
