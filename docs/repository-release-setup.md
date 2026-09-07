# Repository release setup

The release workflow requires a protected default branch, an effective GitHub Actions
**Package gate** requirement, and immutable publication.
Repository settings must be established once by a GitHub administrator; merging
workflow code does not create those settings. The GitHub connector used to prepare
these changes has no repository administration capability.

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
Without `--apply`, every API request is read-only. Exit status 0 means configured,
1 means changes are needed, and 2 means a request or validation failed. A failure
does not hide results for subsequent repositories. Applying is idempotent, and
each mutation is followed by a read that verifies the result.

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

Apply this configuration and complete the administrator `--check` audit first. Then
rerun the repair PR checks: the live **Release prerequisites** job must observe the
protected current default branch and an effective rule requiring **Package gate**
from GitHub Actions. The aggregate Package gate also requires package tests and
release automation tests. Access Control and Business Definition additionally
require the live **Dependency release prerequisites** job. A failed or skipped
required job blocks the aggregate. Rebase only after the complete PR gate succeeds.

The required check names a job, not a source commit. The release workflow repeats
the complete gate on the new default-branch commit created by rebase, and tags that
tested commit. No PR head SHA belongs in the ruleset or release configuration.
Changing repository settings does not rerun an earlier failed check automatically;
rerun the failed checks or dispatch the release workflow on the default branch.

The workflow's Contents permission can verify branch protection and effective
required checks, but cannot read the immutable-release administration setting.
The administrator `--check` is therefore required even when Release prerequisites
succeeds. A green prerequisite check does not establish that every package is
ready to publish. Exact dependency releases and independent evidence must also
pass where required, and actual publication must succeed before declaring a
release complete.

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
