# Package release standard

This is the common Kumwe package release contract. Apply it to every new extraction.

## One quality gate

The repository's CI workflow is callable. Pull requests and release runs execute the same
PHP matrix, package-owned behavior/boundary/conformance tests, static checks, API/manifest
checks, production autoload smoke and clean archive consumer checks. The stable required
check is **Package gate**. It requires package checks and release automation regression
tests to succeed; failure or a skipped required job blocks the aggregate. Package-specific
runtime requirements and Studio evidence remain part of their owning package.

Source CI verifies the proposed code and package. Live repository configuration and
independent upstream release evidence are checked before publication, outside Package
gate. This follows the Version 2 evidence states: a complete green implementation PR is
`package-implemented`; observed publication is `package-released`; successful independent
artifact and consumer verification establishes `release-verified`. A source PR can pass
while publication prerequisites remain outstanding. Report those states separately.

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

Repository setup is a publication prerequisite. It is separate from source CI so a repair
PR can pass its complete Package gate while settings are being configured. Contents access
cannot inspect the immutable-release administration setting; the administrator audit
verifies it. To apply setup and request release runs on each discovered default branch:

```bash
bash tools/configure-release-repositories.sh --apply --dispatch
```

The script verifies settings before dispatch. A dispatch requests a workflow run; it does
not prove publication. Follow each run and its logs, then verify the actual release.

Committing a ruleset JSON file does not activate it. Packagist registration does not
configure branch protection or immutable releases. Missing settings are reported as
release prerequisites, never disguised as unit-test failures or a successful publication.

## Publication, retries and package contents

Publication is serialized per branch, with pending default-branch releases queued. The
helper checks live branch protection, then verifies or creates the exact semantic tag
and publishes in the same run; a tag made by GITHUB_TOKEN
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

Access Control and Business Definition verify upstream evidence before publication.
After a production Composer install, their release jobs check the exact resolved Kumwe
dependencies against live immutable releases, tag/source/dist references and independent
external attestations. A mutable selected dependency or missing evidence blocks
publication. Source CI still runs package tests and the dependency checker's offline
regressions. Updating repository settings does not repair historical exact dependency
pins or create attestations; select independently verified successor releases when needed.

## Regression requirements

Every package owns the common Bash release transition and setup fixtures. They exercise
real Git histories including a rebase, successful publication and retry, immutable ancestor
verification, annotated tags, mismatched commits, absent protection, mutable releases and
API failures. Update these tests with the automation. Run them before opening the PR and
verify the required Package gate on GitHub. Offline fixtures validate release behavior;
live publication checks establish current repository and dependency prerequisites.
Confirm an actual release only after its default-branch publication run succeeds and
immutable release metadata proves the recorded version was published. Complete the
independent verification before declaring `release-verified` or adopting the package.
