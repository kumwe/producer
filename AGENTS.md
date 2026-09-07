# Package maintenance

Read docs/package-release-standard.md before changing CI, release automation, package
metadata or extraction handoff evidence. The reusable CI gate must be identical for
PRs and post-rebase default-branch releases. Never pin this PR's SHA as a future
release identity, bypass package tests or replace existing tags/releases.

Preserve package-owned behavior, boundary and conformance tests and clean no-dev
archive consumer verification. Keep the common release helpers and their regression
fixtures aligned with the documented standard across Kumwe package repositories.

Run the existing complete package quality command and release automation regressions.
Open a branch and PR; maintainers retain merge and release authority.
