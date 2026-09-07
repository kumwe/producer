# Package release standard

This is the common Kumwe package release contract. Apply it to every new extraction.

## One quality gate

The repository's CI workflow is callable. Pull requests and release runs execute the same
PHP matrix, package-owned behavior/boundary/conformance tests, static checks, API/manifest
checks, production autoload smoke and clean archive consumer checks. The stable required
check is **Package gate**. It requires package checks, release regression tests and the
live **Release prerequisites** job to succeed; failure or a skipped required job blocks
the aggregate. Access Control and Business Definition also require the live
**Dependency release prerequisites** job. Package-specific runtime requirements and
Studio evidence remain part of their owning package.

Release prerequisites discovers the current default branch, verifies its live protected
state and checks that its effective rules require **Package gate** from GitHub Actions.
A checked-in ruleset, a protection flag alone, or a passing offline fixture does not
establish that the required check is enforced. This read-only job runs before merge and
again in the post-rebase release gate. It does not need an administration credential.

## Rebases and release identity

Human maintainers rebase PRs into the repository's default branch. Release automation
discovers that branch dynamically, reruns the complete gate on the resulting commit,
checks out the event's exact github.sha, and verifies local HEAD matches it. A PR commit
SHA is never embedded as the future release identity. Historical extraction baselines,
third-party action pins and independently verified dependency release SHAs retain their
separate provenance purpose.

The newest stable changelog record selects the version. An Unreleased-only changelog
does not publish. An empty Unreleased section before a recorded version is supported.
The recorded version and package release manifests must agree before merge.

## Repository setup

Run the same administrator setup once for the package family, from any checkout:

```bash
bash tools/configure-release-repositories.sh --check
bash tools/configure-release-repositories.sh --apply
bash tools/configure-release-repositories.sh --check
```

The setup manages its own named ruleset, requires PRs and the Package gate, prohibits
default-branch deletion/force pushes, permits linear rebase merges, and enables immutable
releases. It preserves unrelated rulesets. It needs an existing authenticated GitHub CLI
session with repository Administration access. The workflow itself uses Contents access
and must never store an administration credential.

Apply the administrator settings and complete the administrator audit first. Then rerun
the repair PR checks so the live prerequisites are observed with the new settings, and
rebase only after the complete Package gate succeeds. Contents access cannot verify the
repository's immutable-release setting; the administrator `--check` remains required.
The live prerequisite job proves the branch/check policy, not complete release readiness.

Committing a ruleset JSON file does not activate it. Packagist registration does not
configure branch protection or immutable releases. Missing settings are reported as
release prerequisites, never disguised as unit-test failures or a successful publication.

## Publication, retries and package contents

Publication is serialized per branch, with pending default-branch releases queued. The helper checks live
branch protection, then verifies or
creates the exact semantic tag and publishes in the same run; a tag made by GITHUB_TOKEN
does not trigger another workflow. Both lightweight and annotated tags are resolved to
commits. Only a confirmed HTTP 404 permits creation; authentication, rate-limit and server
errors fail without mutations. Existing tags and releases are never moved, deleted or
replaced. A published immutable release on an ancestor is verified on subsequent pushes.
An unpublished tag can be completed only when its commit is the fully tested event commit.

Historical mutable releases remain historical. Enabling immutability does not repair old
releases: record an unused successor version with updated package manifests and let the
post-rebase gate publish it. Use Run workflow on the default branch to retry after setup.

Composer and GitHub source archives obey the repository's export exclusions. Source,
runtime resources and public manifests remain package-specific; development tools and
release automation must stay excluded. Packagist follows the semantic tags. Preserve the
clean no-dev archive consumer gate; do not substitute a path repository or source fallback.

## Upstream release evidence

Access Control and Business Definition run **Dependency release prerequisites** in CI
and repeat the check before publication. After a production Composer install, they verify
the exact resolved Kumwe dependencies against live immutable releases, tag/source/dist
references and independent external attestations. The aggregate cannot pass while a
selected dependency is mutable or its evidence is missing. A stale text flag cannot prove
a release, and an unconditional failure cannot discover a repaired one. Their existing
unresolved evidence remains blocked until real successor releases and independent
verification are available. Updating repository settings alone does not repair old exact
dependency pins or create attestations.

## Regression requirements

Every package owns the common Bash release transition and setup fixtures. They exercise
real Git histories including a rebase, successful publication and retry, immutable ancestor
verification, annotated tags, mismatched commits, absent protection, mutable releases and
API failures. Update these tests with the automation. Run them before opening the PR and
verify the required Package gate on GitHub, including the live prerequisite jobs. Offline
fixtures cannot establish current repository configuration or dependency publication.
Do not report all packages as release-ready from unit tests or a green PR alone. Confirm
the administrator setup audit and each package's outstanding dependency evidence. Report
an actual release as successful only after its default-branch publication run succeeds
and its immutable release metadata proves the recorded version was published.
