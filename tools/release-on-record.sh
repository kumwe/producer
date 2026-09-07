#!/usr/bin/env bash
# Publish only the exact default-branch checkout that passed this workflow's package gate.
set -euo pipefail

fail() { echo "::error::$*" >&2; exit 1; }
output() { if [[ -n "${GITHUB_OUTPUT:-}" ]]; then printf '%s=%s\n' "$1" "$2" >> "$GITHUB_OUTPUT"; fi; }

tools_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
: "${GITHUB_REPOSITORY:?GITHUB_REPOSITORY is required}"
: "${GITHUB_REF:?GITHUB_REF is required}"
: "${GITHUB_SHA:?GITHUB_SHA is required}"
: "${DEFAULT_BRANCH:?DEFAULT_BRANCH is required}"
[[ "$GITHUB_REPOSITORY" =~ ^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]] || fail 'Invalid repository coordinate.'
git check-ref-format "refs/heads/$DEFAULT_BRANCH" || fail 'Invalid default branch.'
[[ "$GITHUB_REF" == "refs/heads/$DEFAULT_BRANCH" ]] || fail 'Only the repository default branch may release.'
[[ "$GITHUB_SHA" =~ ^[0-9a-f]{40}$ ]] || fail 'The tested source must be an exact commit SHA.'
cd -- "$(git rev-parse --show-toplevel)"
[[ "$(git rev-parse HEAD)" == "$GITHUB_SHA" ]] || fail 'The checkout differs from the tested workflow commit.'
git diff --quiet HEAD -- || fail 'Release checkout contains tracked modifications.'

version="$(bash "$tools_dir/read-release-record.sh" < CHANGELOG.md)"
output version "$version"
if [[ -z "$version" ]]; then
  echo '::notice::No version is recorded; nothing to release.'
  output release_status no-record
  exit 0
fi

scratch_dir="$(mktemp -d)"
trap 'rm -rf -- "$scratch_dir"' EXIT
api_body="$scratch_dir/body.json"

# Require a real HTTP 404; text in an error message must never authorize creation.
# Return 4 only for confirmed absence, and fail closed on every other API failure.
api_read() {
  local endpoint="$1" status=0 first_line
  gh api --include "$endpoint" > "$scratch_dir/response" 2> "$scratch_dir/error" || status=$?
  IFS= read -r first_line < "$scratch_dir/response" || true
  first_line="${first_line%$'\r'}"
  [[ "$first_line" =~ ^HTTP/[0-9.]+[[:space:]]([0-9]{3})([[:space:]]|$) ]] \
    || fail "Cannot verify the HTTP status returned for $endpoint."
  local http_status="${BASH_REMATCH[1]}"
  sed '1,/^\r\{0,1\}$/d' "$scratch_dir/response" > "$api_body"
  if [[ "$http_status" == 404 && "$status" -ne 0 ]]; then
    return 4
  fi
  [[ "$status" -eq 0 && "$http_status" == 200 ]] || fail "GitHub API refused $endpoint (HTTP $http_status)."
  jq -es 'length == 1 and (.[0] | type == "object")' "$api_body" >/dev/null \
    || fail "Invalid JSON metadata returned for $endpoint."
}

require_protection() {
  local branch_path
  branch_path="$(jq -rn --arg branch "$DEFAULT_BRANCH" '$branch | @uri')"
  api_read "repos/$GITHUB_REPOSITORY/branches/$branch_path" \
    || fail 'The default branch cannot be read.'
  bash "$tools_dir/check-release-integrity.sh" protected-branch "$DEFAULT_BRANCH" < "$api_body"
}

resolve_tag_commit() {
  local depth=0 object_type
  while :; do
    object_type="$(jq -r '.object.type' "$api_body")"
    tag_sha="$(jq -r '.object.sha' "$api_body")"
    [[ "$tag_sha" =~ ^[0-9a-f]{40}$ ]] || fail "Tag v$version has an invalid target SHA."
    if [[ "$object_type" == commit ]]; then break; fi
    [[ "$object_type" == tag ]] || fail "Tag v$version does not resolve to a commit."
    depth=$((depth + 1))
    [[ "$depth" -le 16 ]] || fail 'Annotated tag nesting exceeds the verification limit.'
    api_read "repos/$GITHUB_REPOSITORY/git/tags/$tag_sha" || fail 'Cannot resolve the annotated tag.'
  done
}

require_protection
release_exists=false
probe_status=0
api_read "repos/$GITHUB_REPOSITORY/releases/tags/v$version" || probe_status=$?
if [[ "$probe_status" -eq 0 ]]; then
  bash "$tools_dir/check-release-integrity.sh" published "$version" < "$api_body"
  release_exists=true
elif [[ "$probe_status" -ne 4 ]]; then
  fail "Cannot determine whether release v$version exists."
fi

probe_status=0
release_sha="$GITHUB_SHA"
api_read "repos/$GITHUB_REPOSITORY/git/ref/tags/v$version" || probe_status=$?
if [[ "$probe_status" -eq 0 ]]; then
  resolve_tag_commit
  git cat-file -e "$tag_sha^{commit}" || fail 'The tag commit is missing from the complete checkout.'
  git merge-base --is-ancestor "$tag_sha" "$GITHUB_SHA" \
    || fail "Tag v$version is outside the tested default-branch history."
  tagged_version="$(git show "$tag_sha:CHANGELOG.md" | bash "$tools_dir/read-release-record.sh")"
  [[ "$tagged_version" == "$version" ]] || fail "Tag v$version has a different release record."
  if [[ "$release_exists" != true && "$tag_sha" != "$GITHUB_SHA" ]]; then
    fail "Unpublished tag v$version must target the exact commit tested in this run; use a new release record."
  fi
  release_sha="$tag_sha"
  echo "Verified tag v$version at $tag_sha."
elif [[ "$probe_status" -eq 4 ]]; then
  [[ "$release_exists" != true ]] || fail 'The published release has no matching version tag.'
  require_protection
  gh api --method POST "repos/$GITHUB_REPOSITORY/git/refs" \
    --field ref="refs/tags/v$version" --field sha="$GITHUB_SHA" > "$scratch_dir/created-tag.json"
  jq -es --arg ref "refs/tags/v$version" --arg sha "$GITHUB_SHA" '
    length == 1 and (.[0] | .ref == $ref and .object.type == "commit" and .object.sha == $sha)
  ' "$scratch_dir/created-tag.json" >/dev/null || fail 'The created tag differs from the tested commit.'
else
  fail "Cannot determine whether tag v$version exists."
fi

if [[ "$release_exists" != true ]]; then
  require_protection
  gh release create "v$version" --repo "$GITHUB_REPOSITORY" --verify-tag --title "v$version" \
    --notes "${RELEASE_NOTES:-See CHANGELOG.md section $version for this package release.}"
fi
api_read "repos/$GITHUB_REPOSITORY/releases/tags/v$version" || fail 'Published release cannot be read.'
bash "$tools_dir/check-release-integrity.sh" published "$version" < "$api_body"
# Publication locks the tag. Verify its final target after that lock has taken effect,
# since the unsealed tag could have changed after our pre-publication inspection.
api_read "repos/$GITHUB_REPOSITORY/git/ref/tags/v$version" || fail 'The immutable release tag cannot be read.'
resolve_tag_commit
[[ "$tag_sha" == "$release_sha" ]] || fail 'The immutable release tag differs from the verified source commit.'
if [[ "$release_exists" == true ]]; then
  output release_status verified
else
  output release_status published
fi
echo "Verified immutable release v$version."
