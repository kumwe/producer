# Releasing Producer

Follow the [Package release standard](package-release-standard.md) for the shared
quality gate, changelog parsing, publication and retry behavior. Complete the
[repository release setup](repository-release-setup.md) with an administrator
session before merging a release record:

```bash
bash tools/configure-release-repositories.sh --check kumwe/producer
bash tools/configure-release-repositories.sh --apply kumwe/producer
```

The required CI check is **Package gate**. Maintainers rebase reviewed PRs into the
repository's current default branch; the release workflow reruns the same quality
gate on the resulting commit and derives its release identity from that run.
A release intention in CHANGELOG.md is not evidence that publication occurred.
Keep work that is not ready for publication under `## Unreleased`.

Producer versions independently under semantic versioning; alignment with Studio
travels through the pin, never through matching version numbers.

## Studio publication prerequisites

The pinned Studio record must be release-ready before a Producer version is
recorded. Package provenance, manifest-verified browser assets and a locally
reproduced outer archive are required, but do not replace the two exact governed
GitHub release assets. A blocked pin stays under `## Unreleased`.
`php tools/verify-release-ready.php` remains a package-specific release gate.
Release notes identify the exact Studio pin implemented by the tagged package.

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

## Publication evidence and recovery

The maintainer performs the initial Packagist submission. Its GitHub integration
then follows tags without a registry credential in CI. Before dependent publication
or App adoption, a fresh independent verifier must bind the exact published
source/tag, archive digest, manifests, registry coordinate, license/security and
clean-consumer results in an external RELEASE-ATTESTATION.yaml. The artifact and
handoff must not invent their own final commit, checksum or publication evidence.

Use the current release workflow on the default branch to retry after correcting
repository settings. Historical mutable releases remain unchanged: enabling
immutability affects future publications, so a mutable version requires an unused
successor. Never move or delete a published tag or replace a released artifact.
An unpublished tag can be completed only on the exact commit tested by the retry.
A green PR does not replace the default-branch release result or independent
verification. Administrator credentials do not belong in Actions.
