# Optional repository release hardening

Normal package publication runs the full source gate and verifies stable version, tag
and source identity. It does not require branch protection, GitHub's immutable-release
flag or this administrator helper. Existing GitHub rules and permissions still apply.
Publication workflows never change repository settings automatically.

Administrators may opt into a policy requiring PRs, **Package gate**, protected default
branches and immutable future releases. `tools/configure-release-repositories.sh` audits
or applies that policy independently of normal source CI and publication.

## Audit or apply the optional policy

Run from a package checkout containing the common tools. With no repository arguments,
the helper covers conversion, producer, extension-sdk, business-definition, access-control,
access-context, transaction, sequence, contribution, localization, canonical-json,
computation and secret-envelope. Explicit `OWNER/REPO` arguments select a subset or add
future packages without changing the script.

Read-only audit:

```bash
bash tools/configure-release-repositories.sh --check
```

Apply the policy when an administrator chooses to enable it:

```bash
bash tools/configure-release-repositories.sh --apply
```

The helper needs Bash 4 or newer, `jq`, and GitHub CLI authenticated as a repository
administrator. Audit needs Administration read permission; applying needs Administration
write. Credentials remain managed by `gh`; do not put an administration token in the
script or publication workflow. The default mode and `--check` are read-only.

Exit status 0 means this optional policy was verified, 1 means policy differences exist,
and 2 means a request or validation failed. Policy differences do not themselves block
normal publication. A failure does not hide results for later repositories. Applying is
idempotent, and each mutation is followed by a read that verifies the result.

## Managed settings

The ruleset is named **Kumwe package release standard**. Its `~DEFAULT_BRANCH` selector
follows default-branch renames. It requires PRs, rebase merging, linear history and the
aggregate **Package gate** status from GitHub Actions. It blocks default-branch force
pushes and deletion, without bypass actors or an added mandatory review count. Feature
branches can still rebase. The helper enables repository rebase merging if necessary
and preserves other merge settings.

Unrelated rulesets remain intact. Within the managed ruleset, stricter review settings,
extra required checks and additional rule types are retained. Duplicate managed names
or a matching inherited ruleset are reported instead of overwritten. A checked-in ruleset
file does not activate settings; Packagist registration does not configure them either.

**Package gate** requires package checks and release automation regressions. A failed or
skipped required source job blocks it. Release runs repeat the same gate on the actual
default-branch commit after rebase. No PR head SHA belongs in the ruleset or release
configuration. Independent attestations remain separate `release-verified` evidence.

Enabling GitHub immutable releases affects future publications; historical mutable
releases remain mutable. Never move a released tag or replace its artifact. If a
platform-immutable successor is desired, record an unused version and publish it through
the complete gate. Upload intended assets while the release is a draft; an immutable
published release locks its tag and assets. Report that state only after GitHub confirms it.

## Optional dispatch and ordinary retries

Administrators who also want to request release runs after applying and verifying the
optional policy can use:

```bash
bash tools/configure-release-repositories.sh --apply --dispatch
```

This mode needs Actions write permission. It discovers each current default branch and
dispatches `release-on-record.yml` after verifying the selected policy. Dispatch means
GitHub accepted a run request; inspect that run and its logs to establish publication.
Changing settings alone does not rerun earlier workflows.

For an ordinary publication retry, use **Run workflow** for `release-on-record.yml` on
the repository's current default branch. No administrator setup is required by the
publisher. From an individual checkout, inspect the resulting runs with:

```bash
gh run list --workflow release-on-record.yml
gh run watch --exit-status
```

Access Control and Business Definition also verify their selected upstream package
release and Composer identities before publication. Their separate strict evidence audit
supports independent verification and downstream adoption; normal publication does not
require an attestation. Confirm the actual release and tag before reporting publication.

## Helper references and fixtures

The optional helper uses GitHub's documented [immutable-release settings][immutable]
and [repository rulesets][rulesets] endpoints.

[immutable]: https://docs.github.com/en/rest/repos/repos#enable-immutable-releases
[rulesets]: https://docs.github.com/en/rest/repos/rules#create-a-repository-ruleset

Run the isolated helper fixtures with:

```bash
bash tools/test-configure-release-repositories.sh
```

The fixtures replace `gh` locally and never contact GitHub. They cover audits, creation,
idempotency, updates, stricter-policy preservation, default-branch renames, denied
requests, pagination, duplicate/inherited names, input validation, administrator access,
the complete default repository list and optional dispatch behavior.
