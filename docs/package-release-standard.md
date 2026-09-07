# Package release standard

This is the common Kumwe package release contract. Apply it to every new extraction.

## One quality gate

Pull requests and default-branch release runs execute the same reusable CI: the PHP
matrix, package-owned behavior/boundary/conformance tests, static checks, API/manifest
checks, production autoload smoke and clean archive consumer checks. **Package gate**
requires the package checks and release automation regression tests to succeed; a failed
or skipped required job blocks the aggregate. Package-specific runtime requirements and
Studio evidence remain part of their owning package.

A complete implementation with green source CI is `package-implemented`. Observed
publication establishes `package-released`. Independent artifact and clean-consumer verification establishes
`release-verified`. Keep these evidence states separate: publication does not supply its
own independent attestation, and an attestation is not a normal publication prerequisite.

## Rebases and release identity

Maintainers rebase reviewed PRs into the repository's current default branch. Release
automation discovers that branch dynamically, reruns the complete gate on the resulting
commit, checks out the event's exact `github.sha`, and verifies local HEAD matches it.
A PR commit SHA is never embedded as the future release identity. Historical extraction
baselines, third-party action pins and verified dependency SHAs retain their own purpose.

The newest stable changelog record selects the version. An Unreleased-only changelog
publishes nothing. An empty Unreleased section before a recorded version is supported.
The recorded version and package release manifests must agree before merge.

## Publication, retries and package contents

Publication is serialized per branch, with pending default-branch release runs queued.
The helper verifies or creates the exact semantic tag and publishes in the same run;
a tag created with `GITHUB_TOKEN` does not trigger a second workflow. Lightweight and
annotated tags are resolved to commits. Only a confirmed HTTP 404 permits creation;
authentication, rate-limit and server errors fail without authorizing that creation.

Existing tags and releases are never moved, deleted or replaced. An existing published
release must match its stable version tag and source identity. Its tag may be an ancestor
of the tested default-branch commit when later changes retain the same release record.
An unpublished tag can be completed only at the exact commit tested by the current run.
The helper checks the final published release and tag again before reporting success.
Use the current workflow on the default branch to retry a failed publication.

Normal publication does not require branch protection, a managed ruleset, GitHub's
optional immutable-release flag or an external attestation. Existing GitHub rules and
permissions still apply; automation does not bypass them or change repository settings.
A published release is described as platform-immutable only when GitHub confirms that
state. Accepting an existing mutable release never grants permission to replace it.

Composer and GitHub source archives obey the repository's export exclusions. Source,
runtime resources and public manifests remain package-specific; development tools and
release automation stay excluded. Packagist follows semantic tags. Preserve the clean
no-dev archive consumer gate; do not substitute a path repository or source fallback.

## Upstream package identity and independent evidence

Access Control and Business Definition run `tools/check-package-dependencies.sh` after
production Composer installation and before publication. Every selected Kumwe runtime
dependency must have a published stable release whose version tag resolves to the exact
Composer source and dist commit. Direct Kumwe requirements must use exact stable versions.
The checker validates these live identities without requiring GitHub immutability or
external attestations. Dependency identity fixtures remain part of source CI.

`tools/check-release-dependencies.sh` remains a separate, optional strict audit in those
packages. It additionally requires platform-immutable releases and independent evidence,
including released-commit workflow and clean-consumer observations. Use that evidence for
`release-verified` and downstream adoption decisions under the extraction handoff protocol.
Normal publication does not clear unresolved evidence or change those adoption states.

## Optional repository hardening

Administrators may use the [repository setup helper](repository-release-setup.md) to
require PRs and **Package gate**, protect the default branch and enable GitHub immutable
releases. Its default `--check` is read-only; only an explicit `--apply` changes settings.
The helper's `--apply --dispatch` mode applies the optional policy and requests release
runs after verifying it. Ordinary release runs do not invoke this helper. Repository
hardening is independent of package publication and source CI; its absence is not a
package-test failure. Administrator credentials do not belong in publication workflows.

## Regression requirements

Keep the common release transition fixtures aligned across packages. They cover real Git
histories with rebases, publication, retries, ancestor tags, annotated tags, mismatched
commits, optional platform policies and API failures. Preserve the separate setup helper
fixtures and, where applicable, both dependency identity and strict evidence audit fixtures.
Run the complete package quality command and release regressions before opening the PR,
then verify **Package gate** on GitHub and again on the actual default-branch release run.

Confirm publication from the successful default-branch run and the actual release/tag
metadata. Report platform immutability only when observed. Complete independent evidence
before declaring `release-verified` or marking the package ready for downstream adoption.
