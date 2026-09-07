# Repository release setup

The repository release configuration requires a protected default branch, the GitHub
Actions **Package gate** check, and immutable publication. A GitHub administrator
establishes these settings once; merging workflow code does not create them. Source CI
tests package code and release automation independently of these live settings.

Run this from any package checkout containing the common tools. The default audit
covers conversion, producer, extension-sdk, business-definition, access-control,
access-context, transaction, sequence, contribution, localization, canonical-json,
computation and secret-envelope. Explicit `OWNER/REPO` arguments select a subset
or add future packages without editing the script.

```bash
bash tools/configure-release-repositories.sh --check
bash tools/configure-release-repositories.sh --apply
bash tools/configure-release-repositories.sh --check
```

The script needs Bash 4 or newer, `jq`, and an authenticated GitHub CLI (`gh auth
login`) acting as a repository administrator. Use credentials with repository
Administration read permission for auditing, or Administration write for applying.
Credentials remain managed by `gh`; do not put a token in this script or workflow.
The default audit and `--check` are read-only. Exit status 0 means setup was verified,
1 means changes are needed, and 2 means a request or validation failed. A failure
does not hide results for subsequent repositories. Applying is idempotent, and
each mutation is followed by a read that verifies the result.

To apply setup and then request release runs with the verified settings:
The new `--dispatch` option requires this updated PR checkout until the repair is merged.

```bash
bash tools/configure-release-repositories.sh --apply --dispatch
```

Dispatch also requires Actions write permission. The script discovers each repository's
current default branch and dispatches `release-on-record.yml` after verifying its setup.
A successful dispatch means GitHub accepted a run request, not that a release completed.
Follow the runs and logs in Actions. From an individual repository checkout, use:

```bash
gh run list --workflow release-on-record.yml
gh run watch --exit-status
```

The managed ruleset is named **Kumwe package release standard**. Its
`~DEFAULT_BRANCH` selector follows a default-branch rename automatically. It
requires pull requests, rebase merging, linear history, and the aggregate
**Package gate** status from GitHub Actions. It blocks default-branch force pushes
and deletion, with no bypass actors and no mandatory review count added by this
baseline. Feature branches remain free to rebase. The repository rebase-merge
setting is enabled if necessary; other repository merge settings are preserved.
Unrelated rulesets are left intact. Inside the managed ruleset, stricter existing
reviews, extra required checks, and additional rule types are retained. A duplicate
managed name or a matching inherited ruleset is reported for administrator review
instead of overwritten.

The aggregate **Package gate** requires package tests and release automation regressions.
A failed or skipped required source job blocks it. Repository configuration and live
dependency release evidence are publication checks, so they do not prevent source repair
PRs from passing CI. The Version 2 state `package-implemented` describes a complete green
implementation PR; `release-verified` requires a published artifact and separate
independent verification. Merge readiness and release verification remain distinct.

The required check names a job, not a source commit. The release workflow repeats
the complete gate on the new default-branch commit created by rebase, and tags that
tested commit. No PR head SHA belongs in the ruleset or release configuration.
Changing repository settings does not rerun an earlier failed workflow automatically;
rerun that workflow or use `--apply --dispatch` to request a fresh default-branch run.

The workflow's Contents permission can verify live branch protection but cannot read
the immutable-release administration setting. The administrator audit verifies that
setting. Access Control and Business Definition additionally verify exact upstream
releases and independent evidence before publication. Confirm each publication run
succeeded and its immutable release exists before declaring that package released.

Enabling immutable releases affects future publications. Existing mutable releases
remain mutable; their tags and releases are not deleted or moved. Publish a new
successor version for a historical mutable release that needs an immutable
replacement. Upload every intended asset while the new release is still a draft,
then publish it. Once a release is immutable, its assets and tag cannot be changed.

The implementation uses GitHub's documented repository endpoints:

- [Immutable release settings: GET to inspect and PUT to enable][immutable].
- [Repository rulesets and the dynamic default-branch selector][rulesets].

[immutable]: https://docs.github.com/en/rest/repos/repos#enable-immutable-releases
[rulesets]: https://docs.github.com/en/rest/repos/rules#create-a-repository-ruleset

Run the isolated fixtures with:

```bash
bash tools/test-configure-release-repositories.sh
```

The fixtures replace `gh` locally and never contact GitHub. They cover read-only
audits, creation, idempotency, updates, preservation of stricter policies,
default-branch renames, denied requests, pagination, duplicate/inherited names,
input validation, administrator access, and the complete default repository list.
