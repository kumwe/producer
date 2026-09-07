#!/usr/bin/env bash
# Audit by default. Only --apply sends repository administration mutations.
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage: bash tools/configure-release-repositories.sh [--check|--apply] [OWNER/REPO ...]

Without repository arguments, audits the 13 extracted Kumwe packages.
--check  Read-only audit (default); exit 1 means configuration drift.
--apply  Enable immutable releases and the managed package ruleset.

Requires Bash 4+, jq, GitHub CLI, and an authenticated repository administrator.
Exit codes: 0 = configured; 1 = changes needed; 2 = request/validation failure.
USAGE
}

mode=check
mode_selected=false
repositories=()
while (($#)); do
  case "$1" in
    --check|--apply)
      if [[ "$mode_selected" == true ]]; then
        echo 'Specify --check or --apply once.' >&2
        exit 2
      fi
      mode="${1#--}"
      mode_selected=true
      ;;
    --help|-h) usage; exit 0 ;;
    -*) echo "Unknown option: $1" >&2; exit 2 ;;
    *) repositories+=("$1") ;;
  esac
  shift
done

if ((${#repositories[@]} == 0)); then
  for package in conversion producer extension-sdk business-definition access-control \
    access-context transaction sequence contribution localization canonical-json \
    computation secret-envelope; do
    repositories+=("kumwe/$package")
  done
fi
for repository in "${repositories[@]}"; do
  if [[ ! "$repository" =~ ^[A-Za-z0-9][A-Za-z0-9-]*/[A-Za-z0-9_.-]+$ ]]; then
    echo "Expected OWNER/REPO, received: $repository" >&2
    exit 2
  fi
done
for dependency in gh jq awk mktemp; do
  command -v "$dependency" >/dev/null || { echo "Missing dependency: $dependency" >&2; exit 2; }
done
if ((BASH_VERSINFO[0] < 4)); then
  echo 'Bash 4 or newer is required.' >&2
  exit 2
fi

task_tmp="$(mktemp -d)"
trap 'rm -rf -- "$task_tmp"' EXIT
ruleset_name='Kumwe package release standard'
desired_ruleset="$task_tmp/desired-ruleset.json"
jq -n --arg name "$ruleset_name" '{
  name: $name,
  target: "branch",
  enforcement: "active",
  bypass_actors: [],
  conditions: {ref_name: {include: ["~DEFAULT_BRANCH"], exclude: []}},
  rules: [
    {type: "deletion"},
    {type: "non_fast_forward"},
    {type: "required_linear_history"},
    {type: "pull_request", parameters: {
      allowed_merge_methods: ["rebase"],
      dismiss_stale_reviews_on_push: false,
      require_code_owner_review: false,
      require_last_push_approval: false,
      required_approving_review_count: 0,
      required_review_thread_resolution: false
    }},
    {type: "required_status_checks", parameters: {
      strict_required_status_checks_policy: true,
      do_not_enforce_on_create: false,
      required_status_checks: [{context: "Package gate", integration_id: 15368}]
    }}
  ]
}' > "$desired_ruleset"

fail() { echo "ERROR: $*" >&2; exit 2; }

# Keep HTTP status separate from JSON. In particular, a 403/5xx/network failure
# is never interpreted as a missing setting. gh handles authentication itself.
api() {
  local method="$1" endpoint="$2" input="${3:-}" result=0
  local args=(api --hostname github.com --method "$method" --include
    -H 'Accept: application/vnd.github+json' -H 'X-GitHub-Api-Version: 2026-03-10')
  [[ -z "$input" ]] || args+=(--input "$input")
  gh "${args[@]}" "$endpoint" > "$task_tmp/response" 2> "$task_tmp/error" || result=$?
  api_status="$(awk '/^HTTP\// {gsub(/\r/, "", $2); print $2; exit}' "$task_tmp/response")"
  api_body="$(awk 'body {print; next} /^\r?$/ {body=1}' "$task_tmp/response")"
  if [[ ! "$api_status" =~ ^[0-9]{3}$ ]]; then
    cat "$task_tmp/error" >&2
    fail "$method $endpoint returned no valid HTTP status."
  fi
  if ((result != 0)) && [[ "$api_status" == 2* ]]; then
    cat "$task_tmp/error" >&2
    fail "$method $endpoint failed despite HTTP $api_status."
  fi
}

expect_status() {
  [[ "$api_status" == "$1" ]] || fail "Expected HTTP $1; received $api_status: $api_body"
}

normalize_ruleset() {
  jq -cS '{name, target, enforcement,
    bypass_actors: (.bypass_actors // []), conditions,
    rules: (.rules | sort_by(.type) | map(
      if .type == "pull_request" then
        .parameters |= {allowed_merge_methods, dismiss_stale_reviews_on_push,
          require_code_owner_review, require_last_push_approval,
          required_approving_review_count, required_review_thread_resolution}
      elif .type == "required_status_checks" then
        .parameters |= {strict_required_status_checks_policy,
          do_not_enforce_on_create: (.do_not_enforce_on_create // false),
          required_status_checks: (.required_status_checks | sort_by(.context))}
      else . end))}'
}

configure_repository() {
  local repository="$1" metadata default_branch rebase_enabled immutability_enabled
  local rulesets='[]' page=1 page_count owned_id owned_count ruleset_update=false
  local desired current entry drift=false effective_ruleset="$task_tmp/effective-ruleset.json"
  cp "$desired_ruleset" "$effective_ruleset"
  api GET "repos/$repository"
  expect_status 200
  metadata="$api_body"
  jq -e '.permissions.admin == true and .archived == false and .disabled == false
    and (.default_branch | type == "string" and length > 0)' <<< "$metadata" >/dev/null \
    || fail "$repository requires an administrator connection and an active repository."
  default_branch="$(jq -r '.default_branch' <<< "$metadata")"
  rebase_enabled="$(jq -r '.allow_rebase_merge' <<< "$metadata")"
  [[ "$rebase_enabled" == true || "$rebase_enabled" == false ]] \
    || fail "$repository returned no rebase-merge setting."

  api GET "repos/$repository/immutable-releases"
  case "$api_status" in
    200)
      immutability_enabled="$(jq -r '.enabled' <<< "$api_body")"
      [[ "$immutability_enabled" == true || "$immutability_enabled" == false ]] \
        || fail "$repository returned malformed immutable-release state."
      ;;
    404) immutability_enabled=false ;;
    *) fail "$repository immutable-release lookup failed with HTTP $api_status: $api_body" ;;
  esac

  # Pagination matters: never create a duplicate because a matching ruleset was
  # on a later page. Parent rulesets remain visible, but are never changed.
  while :; do
    api GET "repos/$repository/rulesets?includes_parents=true&per_page=100&page=$page"
    expect_status 200
    jq -e 'type == "array"' <<< "$api_body" >/dev/null || fail 'Malformed ruleset list.'
    page_count="$(jq 'length' <<< "$api_body")"
    rulesets="$(jq -cn --argjson previous "$rulesets" --argjson next "$api_body" '$previous + $next')"
    ((page_count == 100)) || break
    page=$((page + 1))
  done
  owned_count="$(jq --arg name "$ruleset_name" '[.[] | select(.name == $name)] | length' <<< "$rulesets")"
  ((owned_count <= 1)) || fail "$repository has duplicate '$ruleset_name' rulesets; no changes made."
  owned_id=''
  if ((owned_count == 1)); then
    entry="$(jq -c --arg name "$ruleset_name" '.[] | select(.name == $name)' <<< "$rulesets")"
    jq -e --arg repository "$repository" '.source_type == "Repository"
      and ((.source | ascii_downcase) == ($repository | ascii_downcase))
      and (.id | type == "number" and . > 0 and floor == .)' <<< "$entry" >/dev/null \
      || fail "$repository inherits or cannot identify the named ruleset; refusing to replace it."
    owned_id="$(jq -r '.id' <<< "$entry")"
    api GET "repos/$repository/rulesets/$owned_id"
    expect_status 200
    current="$(normalize_ruleset <<< "$api_body")"
    # Keep stricter review settings, extra required checks and all unrelated
    # rule types even inside our named ruleset. Enforce the baseline without
    # weakening protections an administrator added after bootstrap.
    jq --argjson existing "$api_body" '
      .rules |= map(. as $required
        | ([$existing.rules[] | select(.type == $required.type)][0] // {}) as $old
        | if .type == "pull_request" then
            .parameters = (($old.parameters // {}) + .parameters
              | .required_approving_review_count = ([.required_approving_review_count,
                  ($old.parameters.required_approving_review_count // 0)] | max)
              | .dismiss_stale_reviews_on_push = (.dismiss_stale_reviews_on_push
                  or ($old.parameters.dismiss_stale_reviews_on_push // false))
              | .require_code_owner_review = (.require_code_owner_review
                  or ($old.parameters.require_code_owner_review // false))
              | .require_last_push_approval = (.require_last_push_approval
                  or ($old.parameters.require_last_push_approval // false))
              | .required_review_thread_resolution = (.required_review_thread_resolution
                  or ($old.parameters.required_review_thread_resolution // false)))
          elif .type == "required_status_checks" then
            .parameters = (($old.parameters // {}) + .parameters
              | .required_status_checks += [($old.parameters.required_status_checks // [])[]
                  | select(.context != "Package gate")])
          else . end)
      | .rules += [$existing.rules[] | select(.type as $type
          | ["deletion", "non_fast_forward", "required_linear_history", "pull_request", "required_status_checks"]
          | index($type) | not)]
    ' "$desired_ruleset" > "$effective_ruleset"
    desired="$(normalize_ruleset < "$effective_ruleset")"
    [[ "$current" == "$desired" ]] || ruleset_update=true
  else
    ruleset_update=true
  fi

  printf '%s (default branch: %s)\n' "$repository" "$default_branch"
  if [[ "$immutability_enabled" != true ]]; then
    echo '  Enable immutable releases for future publications.'
    drift=true
  fi
  if [[ "$rebase_enabled" != true ]]; then
    echo '  Enable repository rebase merging.'
    drift=true
  fi
  if [[ "$ruleset_update" == true ]]; then
    echo "  Configure '$ruleset_name': PR, rebase, Package gate, no bypass."
    drift=true
  fi
  if [[ "$drift" == false ]]; then
    echo '  Configuration verified; no changes needed.'
    return 0
  fi
  [[ "$mode" == apply ]] || return 1

  if [[ "$immutability_enabled" != true ]]; then
    api PUT "repos/$repository/immutable-releases"
    expect_status 204
    api GET "repos/$repository/immutable-releases"
    expect_status 200
    jq -e '.enabled == true' <<< "$api_body" >/dev/null || fail 'Immutable-release verification failed.'
  fi
  if [[ "$rebase_enabled" != true ]]; then
    printf '{"allow_rebase_merge":true}\n' > "$task_tmp/rebase.json"
    api PATCH "repos/$repository" "$task_tmp/rebase.json"
    expect_status 200
    jq -e '.allow_rebase_merge == true' <<< "$api_body" >/dev/null || fail 'Rebase setting verification failed.'
  fi
  if [[ "$ruleset_update" == true ]]; then
    if [[ -z "$owned_id" ]]; then
      api POST "repos/$repository/rulesets" "$effective_ruleset"
      expect_status 201
      owned_id="$(jq -er '.id | select(type == "number" and . > 0 and floor == .)' <<< "$api_body")"
    else
      api PUT "repos/$repository/rulesets/$owned_id" "$effective_ruleset"
      expect_status 200
    fi
    api GET "repos/$repository/rulesets/$owned_id"
    expect_status 200
    current="$(normalize_ruleset <<< "$api_body")"
    desired="$(normalize_ruleset < "$effective_ruleset")"
    [[ "$current" == "$desired" ]] || fail 'Ruleset verification failed.'
  fi
  echo '  Applied and verified. Existing releases and tags were preserved.'
}

overall=0
for repository in "${repositories[@]}"; do
  # Each repository is isolated so one denied API call does not hide the rest
  # of the audit. Keep errexit active inside the worker subshell.
  set +e
  (set -e; configure_repository "$repository")
  result=$?
  set -e
  if ((result > 1)); then
    overall=2
  elif ((result == 1 && overall == 0)); then
    overall=1
  fi
done
exit "$overall"
