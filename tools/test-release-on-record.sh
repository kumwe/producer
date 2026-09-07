#!/usr/bin/env bash
# Real Git histories plus an isolated GitHub transport prove publication and refusal paths.
# No network, credentials or remote repository mutations are used by these fixtures.
set -euo pipefail

tools_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
fixture_root="$(mktemp -d)"
trap 'rm -rf -- "$fixture_root"' EXIT
mkdir -p "$fixture_root/bin" "$fixture_root/source"
git -C "$fixture_root/source" init -q -b main
git -C "$fixture_root/source" config user.name 'Release fixture'
git -C "$fixture_root/source" config user.email 'release-fixture@example.invalid'
printf '# Changelog\n\n## 0.1.0\n\nInitial package.\n' > "$fixture_root/source/CHANGELOG.md"
git -C "$fixture_root/source" add CHANGELOG.md
git -C "$fixture_root/source" commit -qm 'Initial release record'

cat > "$fixture_root/bin/gh" <<'GH'
#!/usr/bin/env bash
set -euo pipefail
state="$GH_FIXTURE_STATE"
log="$GH_FIXTURE_LOG"
read_state() { jq -r "$1" "$state"; }
update_state() { jq "$1" "$state" > "$state.new"; mv "$state.new" "$state"; }
respond() {
  local code="$1" body="$2"
  if [[ "$(read_state '.malformed_http // false')" == true ]]; then
    printf 'Transport failed mentioning HTTP 404\n%s\n' "$body"
    exit 1
  fi
  printf 'HTTP/2.0 %s Fixture\r\nContent-Type: application/json\r\n\r\n%s\n' "$code" "$body"
  [[ "$code" == 200 ]]
}
if [[ "$1" == api && "$2" == --include ]]; then
  endpoint="$3"
  printf 'GET %s\n' "$endpoint" >> "$log"
  kind=other
  case "$endpoint" in
    */branches/*) kind=branch ;;
    */releases/tags/*) kind=release ;;
    */git/ref/tags/*) kind=tag ;;
    */git/tags/*) kind=annotated ;;
  esac
  if [[ "$(read_state '.error_endpoint // ""')" == "$kind" ]]; then
    respond "$(read_state '.error_http // 403')" '{"message":"Denied; example HTTP 404 is not an HTTP status"}'
    exit
  fi
  if [[ "$(read_state '.invalid_json_endpoint // ""')" == "$kind" ]]; then
    respond 200 '{broken-json'
    exit
  fi
  case "$kind" in
    branch)
      branch="$(git branch --show-current)"
      protected="$(read_state '.protected')"
      if [[ "$(read_state '.unprotect_after_tag // false')" == true ]] \
        && git show-ref --verify -q refs/tags/v0.1.0; then
        protected=false
      fi
      respond 200 "$(jq -n --arg branch "$branch" --argjson protected "$protected" \
        '{name:$branch,protected:$protected}')"
      ;;
    release)
      if [[ "$(read_state '.published')" != true ]]; then respond 404 '{"message":"Not Found"}'; exit; fi
      respond 200 "$(jq '{tag_name:(.release_tag // "v0.1.0"),draft:(.draft // false),
        prerelease:(.prerelease // false),immutable:.immutable,published_at:"2026-01-01T00:00:00Z"}' "$state")"
      ;;
    tag|annotated)
      if [[ "$kind" == tag ]]; then
        ref="refs/tags/${endpoint##*/}"
        target="$(git rev-parse --verify "$ref" 2>/dev/null)" || { respond 404 '{"message":"Not Found"}'; exit; }
        type="$(git cat-file -t "$target")"
      else
        object="${endpoint##*/}"
        target="$(git cat-file tag "$object" | sed -n 's/^object //p')"
        type="$(git cat-file tag "$object" | sed -n 's/^type //p')"
      fi
      override="$(read_state '.tag_type_override // ""')"
      if [[ -n "$override" ]]; then type="$override"; fi
      override="$(read_state '.tag_sha_override // ""')"
      if [[ -n "$override" ]]; then target="$override"; fi
      respond 200 "$(jq -n --arg type "$type" --arg sha "$target" '{object:{type:$type,sha:$sha}}')"
      ;;
    *) echo "Unexpected fixture read: $endpoint" >&2; exit 2 ;;
  esac
elif [[ "$1" == api && "$2" == --method && "$3" == POST && "$4" == */git/refs ]]; then
  [[ "$5" == --field && "$7" == --field && "$#" -eq 8 ]] || exit 2
  ref="${6#ref=}"
  sha="${8#sha=}"
  printf 'MUTATE tag %s %s\n' "$ref" "$sha" >> "$log"
  [[ "$(read_state '.fail_tag_create // false')" != true ]] || exit 1
  git update-ref "$ref" "$sha" 0000000000000000000000000000000000000000
  jq -n --arg ref "$ref" --arg sha "$sha" '{ref:$ref,object:{type:"commit",sha:$sha}}'
elif [[ "$1" == release && "$2" == create ]]; then
  [[ "$4" == --repo && "$5" == kumwe/fixture && "$6" == --verify-tag ]] || exit 2
  [[ "$(read_state '.fail_publish // false')" != true ]] || exit 1
  printf 'MUTATE publish %s\n' "$3" >> "$log"
  git show-ref --verify -q "refs/tags/$3" || exit 1
  update_state '.published = true'
else
  printf 'Unexpected fixture command: %s\n' "$*" >&2
  exit 2
fi
GH
chmod +x "$fixture_root/bin/gh"

case_number=0
assertions=0
new_case() {
  case_number=$((case_number + 1))
  case_dir="$fixture_root/case-$case_number"
  mkdir -p "$case_dir"
  git clone -q --local "$fixture_root/source" "$case_dir/repo"
  git -C "$case_dir/repo" config user.name 'Release fixture'
  git -C "$case_dir/repo" config user.email 'release-fixture@example.invalid'
  printf '{"protected":true,"immutable":true,"published":false}\n' > "$case_dir/state.json"
  : > "$case_dir/calls"
  : > "$case_dir/output"
  tested_sha="$(git -C "$case_dir/repo" rev-parse HEAD)"
  default_branch=main
  event_ref=refs/heads/main
}
state() { jq "$1" "$case_dir/state.json" > "$case_dir/state.new"; mv "$case_dir/state.new" "$case_dir/state.json"; }
commit_file() {
  printf '%s\n' "$2" > "$case_dir/repo/$1"
  git -C "$case_dir/repo" add "$1"
  git -C "$case_dir/repo" commit -qm "Fixture $1"
  tested_sha="$(git -C "$case_dir/repo" rev-parse HEAD)"
}
check() {
  local expected="$1" expected_mutations="$2" label="$3" actual=0 mutations
  (
    cd "$case_dir/repo"
    PATH="$fixture_root/bin:$PATH" GH_FIXTURE_STATE="$case_dir/state.json" GH_FIXTURE_LOG="$case_dir/calls" \
      GITHUB_REPOSITORY=kumwe/fixture DEFAULT_BRANCH="$default_branch" GITHUB_REF="$event_ref" \
      GITHUB_SHA="$tested_sha" GITHUB_OUTPUT="$case_dir/output" bash "$tools_dir/release-on-record.sh"
  ) > "$case_dir/result" 2>&1 || actual=$?
  mutations="$(awk '/^MUTATE / {n++} END {print n+0}' "$case_dir/calls")"
  if [[ ( "$expected" == pass && "$actual" -ne 0 ) || ( "$expected" == fail && "$actual" -eq 0 ) \
    || "$mutations" -ne "$expected_mutations" ]]; then
    echo "FAIL: $label (exit=$actual, mutations=$mutations, expected=$expected/$expected_mutations)" >&2
    cat "$case_dir/result" "$case_dir/calls" >&2
    exit 1
  fi
  assertions=$((assertions + 1))
}

new_case
check pass 2 'missing release and tag publish the tested commit'
[[ "$(git -C "$case_dir/repo" rev-parse refs/tags/v0.1.0)" == "$tested_sha" ]]
grep -q '^release_status=published$' "$case_dir/output"
: > "$case_dir/calls"
check pass 0 'rerun verifies without mutations'
grep -q '^release_status=verified$' "$case_dir/output"

new_case
git -C "$case_dir/repo" branch -m master
default_branch=master
event_ref=refs/heads/master
check pass 2 'dynamic master default branch'

new_case
git -C "$case_dir/repo" branch -m 'stable/release'
default_branch=stable/release
event_ref=refs/heads/stable/release
check pass 2 'default branch with slash is encoded for the API'
grep -q '/branches/stable%2Frelease$' "$case_dir/calls"

new_case
git -C "$case_dir/repo" checkout -qb feature
commit_file feature.txt 'PR work'
pr_sha="$tested_sha"
git -C "$case_dir/repo" checkout -q main
commit_file concurrent.txt 'Concurrent main work'
git -C "$case_dir/repo" checkout -q feature
git -C "$case_dir/repo" rebase -q main
rebased_sha="$(git -C "$case_dir/repo" rev-parse HEAD)"
[[ "$pr_sha" != "$rebased_sha" ]]
git -C "$case_dir/repo" checkout -q main
git -C "$case_dir/repo" merge -q --ff-only feature
tested_sha="$rebased_sha"
check pass 2 'real rebase publishes the new default-branch SHA'
[[ "$(git -C "$case_dir/repo" rev-parse refs/tags/v0.1.0)" == "$rebased_sha" ]]

for tag_kind in lightweight annotated; do
  new_case
  if [[ "$tag_kind" == annotated ]]; then
    git -C "$case_dir/repo" tag -am 'Release fixture' v0.1.0
  else
    git -C "$case_dir/repo" tag v0.1.0
  fi
  check pass 1 "existing $tag_kind tag at tested HEAD is recoverable"

  new_case
  if [[ "$tag_kind" == annotated ]]; then
    git -C "$case_dir/repo" tag -am 'Release fixture' v0.1.0
  else
    git -C "$case_dir/repo" tag v0.1.0
  fi
  state '.published = true'
  commit_file later.txt 'Post-release change without a new version'
  check pass 0 "published $tag_kind tag remains valid after later main commits"
done

new_case
git -C "$case_dir/repo" tag -am 'Inner annotation' inner
git -C "$case_dir/repo" -c advice.nestedTag=false tag -am 'Outer annotation' v0.1.0 inner
check pass 1 'nested annotated tag resolves to the tested commit'

new_case
state '.fail_publish = true'
check fail 1 'publication failure preserves the created tag'
state '.fail_publish = false'
: > "$case_dir/calls"
check pass 1 'retry finishes publication without moving the tag'

new_case
commit_file CHANGELOG.md $'# Changelog\n\n## Unreleased\n\nPending changes.'
state '.protected = false'
check pass 0 'unreleased-only is a no-op before repository API calls'
[[ ! -s "$case_dir/calls" ]]
grep -q '^release_status=no-record$' "$case_dir/output"

new_case
commit_file CHANGELOG.md ''
check pass 0 'empty release record is a no-op'
[[ ! -s "$case_dir/calls" ]]

new_case
commit_file CHANGELOG.md $'# Changelog\n\n## [Unreleased]\n\nWork.\n\n## [0.1.0]\n\nRelease.'
check pass 2 'Unreleased heading still discovers the latest recorded version'

new_case
commit_file CHANGELOG.md $'# Changelog\n\n## invalid\n\n## 0.1.0\n'
check fail 0 'malformed newest release record fails before API calls'
[[ ! -s "$case_dir/calls" ]]

new_case
event_ref=refs/heads/feature
check fail 0 'feature branch event cannot publish'

new_case
event_ref=refs/pull/7/merge
check fail 0 'pull request merge ref cannot publish'

new_case
old_sha="$tested_sha"
commit_file later.txt 'New merged commit'
tested_sha="$old_sha"
check fail 0 'old PR or pre-rebase SHA cannot authorize a different checkout'

new_case
printf 'Local dirty change\n' >> "$case_dir/repo/CHANGELOG.md"
check fail 0 'tracked local edits cannot be published as tested source'

new_case
state '.protected = false'
check fail 0 'unprotected default branch fails closed'

new_case
state '.unprotect_after_tag = true'
check fail 1 'protection is rechecked immediately before publication'

for endpoint in branch release tag; do
  for http in 403 500; do
    new_case
    state ".error_endpoint = \"$endpoint\" | .error_http = $http"
    check fail 0 "$endpoint HTTP $http never authorizes mutation"
  done
  new_case
  state ".invalid_json_endpoint = \"$endpoint\""
  check fail 0 "$endpoint malformed JSON fails closed"
done

new_case
state '.malformed_http = true'
check fail 0 'error text containing HTTP 404 is not a confirmed missing resource'

new_case
state '.error_endpoint = "branch" | .error_http = 404'
check fail 0 'missing default branch cannot publish'

for field in immutable draft prerelease; do
  new_case
  git -C "$case_dir/repo" tag v0.1.0
  state '.published = true'
  if [[ "$field" == immutable ]]; then state '.immutable = false'; else state ".$field = true"; fi
  check fail 0 "existing publication with invalid $field is refused"
done

new_case
state '.immutable = false'
check fail 2 'new publication must actually become immutable'

new_case
state '.published = true'
check fail 0 'published release with missing tag fails without mutation'

new_case
git -C "$case_dir/repo" tag v0.1.0
commit_file later.txt 'Different current tested commit'
check fail 0 'unpublished old tag is never moved or published from another tested commit'

new_case
git -C "$case_dir/repo" checkout -qb unrelated
commit_file unrelated.txt 'Unmerged change'
git -C "$case_dir/repo" tag v0.1.0
git -C "$case_dir/repo" checkout -q main
tested_sha="$(git -C "$case_dir/repo" rev-parse HEAD)"
state '.published = true'
check fail 0 'published tag outside default-branch history is refused'

new_case
commit_file CHANGELOG.md $'# Changelog\n\n## 0.0.9\n'
git -C "$case_dir/repo" tag v0.1.0
commit_file CHANGELOG.md $'# Changelog\n\n## 0.1.0\n'
state '.published = true'
check fail 0 'tagged release record must match the requested version'

new_case
git -C "$case_dir/repo" tag v0.1.0
state '.tag_type_override = "tree"'
check fail 0 'non-commit tag object is refused'

new_case
git -C "$case_dir/repo" tag v0.1.0
state '.tag_sha_override = "invalid"'
check fail 0 'malformed tag SHA is refused'

new_case
state '.fail_tag_create = true'
check fail 1 'failed tag creation never attempts release publication'

printf 'Release workflow: %s Git/HTTP fixture cases passed.\n' "$assertions"
