#!/usr/bin/env bash
# Read-only live prerequisite checks shared by PR and post-rebase CI.
# GitHub's immutable-release setting requires Administration read, which CI does
# not have. Verify it with configure-release-repositories.sh before publication.
set -euo pipefail

fail() { echo "::error::$*" >&2; exit 1; }
: "${GITHUB_REPOSITORY:?GITHUB_REPOSITORY is required}"
: "${DEFAULT_BRANCH:?DEFAULT_BRANCH is required}"
[[ "$GITHUB_REPOSITORY" =~ ^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]] || fail 'Invalid repository coordinate.'
git check-ref-format "refs/heads/$DEFAULT_BRANCH" >/dev/null || fail 'Invalid default branch.'

task_tmp="$(mktemp -d)"
trap 'rm -rf -- "$task_tmp"' EXIT
api_body="$task_tmp/body.json"
branch_path="$(jq -rn --arg branch "$DEFAULT_BRANCH" '$branch | @uri')"

api_read() {
  local endpoint="$1" status=0 first_line http_status
  gh api --hostname github.com --method GET --include \
    -H 'Accept: application/vnd.github+json' -H 'X-GitHub-Api-Version: 2026-03-10' \
    "$endpoint" > "$task_tmp/response" 2> "$task_tmp/error" || status=$?
  IFS= read -r first_line < "$task_tmp/response" || true
  first_line="${first_line%$'\r'}"
  [[ "$first_line" =~ ^HTTP/[0-9.]+[[:space:]]([0-9]{3})([[:space:]]|$) ]] \
    || fail "Cannot verify the HTTP status returned for $endpoint."
  http_status="${BASH_REMATCH[1]}"
  [[ "$status" -eq 0 && "$http_status" == 200 ]] \
    || fail "Cannot verify release prerequisites: $endpoint returned HTTP $http_status."
  sed '1,/^\r\{0,1\}$/d' "$task_tmp/response" > "$api_body"
  jq -es 'length == 1' "$api_body" >/dev/null || fail "Malformed JSON returned for $endpoint."
}

setup_hint='Run bash tools/configure-release-repositories.sh --apply with administrator access, then rerun CI.'
api_read "repos/$GITHUB_REPOSITORY/branches/$branch_path"
jq -e --arg branch "$DEFAULT_BRANCH" \
  'type == "object" and .name == $branch and .protected == true' "$api_body" >/dev/null \
  || fail "Default branch $DEFAULT_BRANCH is not verifiably protected. $setup_hint"

page=1
gate_required=false
while :; do
  api_read "repos/$GITHUB_REPOSITORY/rules/branches/$branch_path?per_page=100&page=$page"
  jq -e 'type == "array" and all(.[]; type == "object")' "$api_body" >/dev/null \
    || fail 'Malformed effective branch rules returned by GitHub.'
  if jq -e 'any(.[]; .type == "required_status_checks" and
    any(.parameters.required_status_checks[]?;
      .context == "Package gate" and .integration_id == 15368))' "$api_body" >/dev/null; then
    gate_required=true
  fi
  [[ "$(jq 'length' "$api_body")" -eq 100 ]] || break
  page=$((page + 1))
done
[[ "$gate_required" == true ]] \
  || fail "Default branch $DEFAULT_BRANCH must require Package gate from GitHub Actions. $setup_hint"

echo "Live branch prerequisites verified for $GITHUB_REPOSITORY ($DEFAULT_BRANCH)."
echo 'Immutable-release configuration remains an administrator setup prerequisite; publication verifies the release.'
