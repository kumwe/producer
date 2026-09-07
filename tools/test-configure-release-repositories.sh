#!/usr/bin/env bash
# All GitHub requests are served by an isolated gh fixture; no network is used.
set -euo pipefail
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
script="$script_dir/configure-release-repositories.sh"
test_tmp="$(mktemp -d)"
trap 'rm -rf -- "$test_tmp"' EXIT
mkdir -p "$test_tmp/bin" "$test_tmp/state"
export MOCK_STATE="$test_tmp/state"
export PATH="$test_tmp/bin:$PATH"

cat > "$test_tmp/bin/gh" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
[[ "$1" == api ]] || exit 90
shift
method=GET input='' endpoint=''
while (($#)); do
  case "$1" in
    --method) method="$2"; shift 2 ;;
    --input) input="$2"; shift 2 ;;
    --hostname|-H) shift 2 ;;
    --include) shift ;;
    *) endpoint="$1"; shift ;;
  esac
done
body='null'
[[ -z "$input" ]] || body="$(cat "$input")"
jq -cn --arg method "$method" --arg endpoint "$endpoint" --argjson body "$body" \
  '{method:$method,endpoint:$endpoint,body:$body}' >> "$MOCK_STATE/calls"
reply() {
  printf 'HTTP/2.0 %s Fixture\r\nContent-Type: application/json\r\n\r\n%s\n' "$1" "${2:-}"
  [[ "$1" == 2* ]] || exit 1
  exit 0
}
if [[ -f "$MOCK_STATE/failure" ]]; then
  failure="$(cat "$MOCK_STATE/failure")"
  if [[ "$endpoint" == "$(jq -r '.endpoint' <<< "$failure")" ]] \
    && [[ "$(jq -r '.method // "any"' <<< "$failure")" == any \
      || "$method" == "$(jq -r '.method' <<< "$failure")" ]]; then
    reply "$(jq -r '.status' <<< "$failure")" '{"message":"fixture failure"}'
  fi
fi
if [[ "$endpoint" == repos/*/*/immutable-releases ]]; then
  if [[ "$method" == PUT ]]; then
    echo true > "$MOCK_STATE/immutable"
    reply 204
  fi
  [[ "$method" == GET ]] || exit 91
  if [[ "$(cat "$MOCK_STATE/immutable")" == true ]]; then
    reply 200 '{"enabled":true,"enforced_by_owner":false}'
  fi
  reply 404 '{"message":"Not Found"}'
elif [[ "$endpoint" == repos/*/*/rulesets\?* ]]; then
  [[ "$method" == GET ]] || exit 92
  page="${endpoint##*page=}"
  start=$(((page - 1) * 100))
  reply 200 "$(jq -c --argjson start "$start" '.[$start:$start+100]' "$MOCK_STATE/rulesets")"
elif [[ "$endpoint" == repos/*/*/rulesets/* ]]; then
  id="${endpoint##*/}"
  if [[ "$method" == PUT ]]; then
    cp "$input" "$MOCK_STATE/rule-$id"
  fi
  [[ -f "$MOCK_STATE/rule-$id" ]] || reply 404 '{"message":"No rule"}'
  reply 200 "$(cat "$MOCK_STATE/rule-$id")"
elif [[ "$endpoint" == repos/*/*/rulesets ]]; then
  [[ "$method" == POST ]] || exit 93
  cp "$input" "$MOCK_STATE/rule-501"
  repository="${endpoint#repos/}"
  repository="${repository%/rulesets}"
  jq --arg source "$repository" \
    '. + [{id:501,name:"Kumwe package release standard",source_type:"Repository",source:$source}]' \
    "$MOCK_STATE/rulesets" > "$MOCK_STATE/next"
  mv "$MOCK_STATE/next" "$MOCK_STATE/rulesets"
  reply 201 '{"id":501}'
elif [[ "$endpoint" == repos/*/*/actions/workflows/release-on-record.yml/dispatches ]]; then
  [[ "$method" == POST ]] || exit 95
  if [[ -f "$MOCK_STATE/dispatch-response" ]]; then
    reply 200 "$(cat "$MOCK_STATE/dispatch-response")"
  fi
  repository="${endpoint#repos/}"
  repository="${repository%/actions/workflows/release-on-record.yml/dispatches}"
  reply 200 "$(jq -cn --arg repository "$repository" '{workflow_run_id:12345,
    run_url:("https://api.github.com/repos/" + $repository + "/actions/runs/12345"),
    html_url:("https://github.com/" + $repository + "/actions/runs/12345")}')"
elif [[ "$endpoint" =~ ^repos/[^/]+/[^/]+$ ]]; then
  if [[ "$method" == PATCH ]]; then
    jq '.allow_rebase_merge = true' "$MOCK_STATE/metadata" > "$MOCK_STATE/next"
    mv "$MOCK_STATE/next" "$MOCK_STATE/metadata"
  fi
  reply 200 "$(cat "$MOCK_STATE/metadata")"
fi
echo "Unexpected fixture endpoint: $method $endpoint" >&2
exit 94
MOCK
chmod +x "$test_tmp/bin/gh"

reset_fixture() {
  rm -f "$MOCK_STATE"/*
  echo '{"permissions":{"admin":true},"archived":false,"disabled":false,
    "default_branch":"main","allow_rebase_merge":true}' > "$MOCK_STATE/metadata"
  echo false > "$MOCK_STATE/immutable"
  echo '[{"id":77,"name":"Existing policy","source_type":"Repository","source":"kumwe/conversion"}]' \
    > "$MOCK_STATE/rulesets"
  : > "$MOCK_STATE/calls"
}

expect_exit() {
  local expected="$1" result=0
  shift
  bash "$script" "$@" > "$test_tmp/stdout" 2> "$test_tmp/stderr" || result=$?
  if ((result != expected)); then
    cat "$test_tmp/stdout" "$test_tmp/stderr" >&2
    echo "Expected exit $expected, received $result: $*" >&2
    exit 1
  fi
}
assert_no_writes() {
  jq -se 'all(.[]; .method == "GET")' "$MOCK_STATE/calls" >/dev/null
}
tests=0
passed() { tests=$((tests + 1)); printf 'ok %s - %s\n' "$tests" "$1"; }

reset_fixture
expect_exit 1 kumwe/conversion
assert_no_writes
passed 'default mode reports missing configuration without mutations'

expect_exit 0 --apply kumwe/conversion
jq -e '.conditions.ref_name.include == ["~DEFAULT_BRANCH"] and .bypass_actors == []
  and .enforcement == "active"
  and ([.rules[].type] | sort) == ["deletion","non_fast_forward","pull_request",
    "required_linear_history","required_status_checks"]
  and any(.rules[]; .type == "pull_request" and .parameters.allowed_merge_methods == ["rebase"]
    and .parameters.required_approving_review_count == 0)
  and any(.rules[]; .type == "required_status_checks" and .parameters.strict_required_status_checks_policy == true
    and .parameters.required_status_checks == [{"context":"Package gate","integration_id":15368}])' \
  "$MOCK_STATE/rule-501" >/dev/null
jq -e 'any(.[]; .id == 77 and .name == "Existing policy")' "$MOCK_STATE/rulesets" >/dev/null
[[ "$(cat "$MOCK_STATE/immutable")" == true ]]
jq -se 'all(.[]; .method != "DELETE" and (.endpoint | contains("/tags/") or contains("/releases/")) == false)' \
  "$MOCK_STATE/calls" >/dev/null
cp "$MOCK_STATE/rule-501" "$test_tmp/desired"
passed 'apply creates the complete ruleset and preserves unrelated rules and tags'

: > "$MOCK_STATE/calls"
expect_exit 0 --apply kumwe/conversion
assert_no_writes
passed 'second apply is idempotent'

jq '.enforcement = "disabled"' "$MOCK_STATE/rule-501" > "$MOCK_STATE/next"
mv "$MOCK_STATE/next" "$MOCK_STATE/rule-501"
: > "$MOCK_STATE/calls"
expect_exit 0 --apply kumwe/conversion
jq -se '[.[] | select(.method != "GET")] | length == 1 and .[0].method == "PUT"
  and .[0].endpoint == "repos/kumwe/conversion/rulesets/501"' "$MOCK_STATE/calls" >/dev/null
passed 'drift updates only the owned ruleset'

jq '.rules += [{type:"required_signatures"}]
  | .rules |= map(if .type == "pull_request" then .parameters.required_approving_review_count = 2
      | .parameters.require_code_owner_review = true
    elif .type == "required_status_checks" then .parameters.required_status_checks += [{context:"Extra security"}]
    else . end)
  | .enforcement = "disabled"' "$MOCK_STATE/rule-501" > "$MOCK_STATE/next"
mv "$MOCK_STATE/next" "$MOCK_STATE/rule-501"
expect_exit 0 --apply kumwe/conversion
jq -e 'any(.rules[]; .type == "required_signatures")
  and any(.rules[]; .type == "pull_request" and .parameters.required_approving_review_count == 2
    and .parameters.require_code_owner_review == true)
  and any(.rules[]; .type == "required_status_checks"
    and any(.parameters.required_status_checks[]; .context == "Extra security"))' \
  "$MOCK_STATE/rule-501" >/dev/null
passed 'stricter reviews, extra checks and additional rules survive managed updates'

reset_fixture
jq '.default_branch = "master" | .allow_rebase_merge = false' "$MOCK_STATE/metadata" > "$MOCK_STATE/next"
mv "$MOCK_STATE/next" "$MOCK_STATE/metadata"
expect_exit 0 --apply kumwe/conversion
grep -q 'default branch: master' "$test_tmp/stdout"
jq -se 'any(.[]; .method == "PATCH" and .body == {allow_rebase_merge:true})' "$MOCK_STATE/calls" >/dev/null
passed 'master and disabled rebase merging are handled without branch hardcoding'

reset_fixture
echo '{"endpoint":"repos/kumwe/conversion/immutable-releases","status":403}' > "$MOCK_STATE/failure"
expect_exit 2 --apply kumwe/conversion
assert_no_writes
passed '403 is not treated as disabled immutability'

reset_fixture
echo '{"endpoint":"repos/kumwe/conversion/immutable-releases","status":503}' > "$MOCK_STATE/failure"
expect_exit 2 --apply kumwe/conversion
assert_no_writes
passed 'server failure refuses mutations'

reset_fixture
echo '[{"id":1,"name":"Kumwe package release standard","source_type":"Repository","source":"kumwe/conversion"},
  {"id":2,"name":"Kumwe package release standard","source_type":"Repository","source":"kumwe/conversion"}]' \
  > "$MOCK_STATE/rulesets"
expect_exit 2 --apply kumwe/conversion
assert_no_writes
passed 'duplicate managed names refuse ambiguous updates'

reset_fixture
echo '[{"id":1,"name":"Kumwe package release standard","source_type":"Organization","source":"kumwe"}]' \
  > "$MOCK_STATE/rulesets"
expect_exit 2 --apply kumwe/conversion
assert_no_writes
passed 'inherited rulesets are never overwritten'

reset_fixture
echo true > "$MOCK_STATE/immutable"
jq -n '[range(100) | {id:.,name:("Existing " + tostring),source_type:"Repository",source:"kumwe/conversion"}]
  + [{id:501,name:"Kumwe package release standard",source_type:"Repository",source:"kumwe/conversion"}]' \
  > "$MOCK_STATE/rulesets"
cp "$test_tmp/desired" "$MOCK_STATE/rule-501"
expect_exit 0 --apply kumwe/conversion
assert_no_writes
jq -se 'any(.[]; .endpoint | endswith("page=2"))' "$MOCK_STATE/calls" >/dev/null
passed 'pagination finds an existing ruleset beyond the first hundred entries'

reset_fixture
expect_exit 2 --apply 'kumwe/conversion/../../another'
[[ ! -s "$MOCK_STATE/calls" ]]
expect_exit 2 --check --apply kumwe/conversion
[[ ! -s "$MOCK_STATE/calls" ]]
passed 'invalid repository paths and conflicting modes fail before requests'

reset_fixture
expect_exit 1 --check
assert_no_writes
jq -se '[.[] | select(.endpoint | test("^repos/[^/]+/[^/]+$"))] | length == 13' "$MOCK_STATE/calls" >/dev/null
passed 'default fleet includes all thirteen extracted packages'

reset_fixture
jq '.permissions.admin = false' "$MOCK_STATE/metadata" > "$MOCK_STATE/next"
mv "$MOCK_STATE/next" "$MOCK_STATE/metadata"
expect_exit 2 --apply kumwe/conversion
assert_no_writes
passed 'a connection without administrator rights cannot apply settings'

reset_fixture
expect_exit 2 --dispatch kumwe/conversion
expect_exit 2 --check --dispatch kumwe/conversion
expect_exit 2 --apply --dispatch --dispatch kumwe/conversion
[[ ! -s "$MOCK_STATE/calls" ]]
passed 'dispatch requires explicit apply and rejects duplicate flags before requests'

reset_fixture
expect_exit 0 --apply --dispatch kumwe/conversion
jq -se '.[-2].method == "GET" and .[-2].endpoint == "repos/kumwe/conversion/rulesets/501"
  and .[-1] == {method:"POST",endpoint:"repos/kumwe/conversion/actions/workflows/release-on-record.yml/dispatches",
    body:{ref:"main"}}' "$MOCK_STATE/calls" >/dev/null
grep -q 'Release workflow queued: https://github.com/kumwe/conversion/actions/runs/12345' "$test_tmp/stdout"
passed 'recovery dispatch follows successful configuration verification and reports the actual run'

: > "$MOCK_STATE/calls"
expect_exit 0 --apply --dispatch kumwe/conversion
jq -se '[.[] | select(.method != "GET")] == [
  {method:"POST",endpoint:"repos/kumwe/conversion/actions/workflows/release-on-record.yml/dispatches",
    body:{ref:"main"}}]' "$MOCK_STATE/calls" >/dev/null
passed 'already configured repositories still dispatch without repeating administration writes'

for branch in master release/stable; do
  jq --arg branch "$branch" '.default_branch = $branch' "$MOCK_STATE/metadata" > "$MOCK_STATE/next"
  mv "$MOCK_STATE/next" "$MOCK_STATE/metadata"
  : > "$MOCK_STATE/calls"
  expect_exit 0 --dispatch --apply kumwe/conversion
  jq -se --arg branch "$branch" '.[-1].body == {ref:$branch}' "$MOCK_STATE/calls" >/dev/null
done
passed 'dispatch dynamically selects master and slash-containing default branch names'

reset_fixture
echo '{"endpoint":"repos/kumwe/conversion/immutable-releases","status":403}' > "$MOCK_STATE/failure"
expect_exit 2 --apply --dispatch kumwe/conversion
assert_no_writes
passed 'denied configuration lookup never dispatches a workflow'

reset_fixture
echo '{"endpoint":"repos/kumwe/conversion/rulesets/501","status":403,"method":"GET"}' > "$MOCK_STATE/failure"
expect_exit 2 --apply --dispatch kumwe/conversion
jq -se 'all(.[]; (.endpoint | endswith("/dispatches")) | not)' "$MOCK_STATE/calls" >/dev/null
passed 'failed verification after applying settings never dispatches a workflow'

reset_fixture
expect_exit 0 --apply kumwe/conversion
: > "$MOCK_STATE/calls"
echo '{"endpoint":"repos/kumwe/conversion/actions/workflows/release-on-record.yml/dispatches",
  "status":403}' > "$MOCK_STATE/failure"
expect_exit 2 --apply --dispatch kumwe/conversion
jq -se '[.[] | select(.method != "GET")] == [
  {method:"POST",endpoint:"repos/kumwe/conversion/actions/workflows/release-on-record.yml/dispatches",
    body:{ref:"main"}}]' "$MOCK_STATE/calls" >/dev/null
passed 'denied dispatch fails without retries, alternate credentials or other writes'

rm "$MOCK_STATE/failure"
echo '{"workflow_run_id":12345,"run_url":"https://api.github.com/repos/unrelated/repo/actions/runs/12345",
  "html_url":"https://github.com/unrelated/repo/actions/runs/12345"}' > "$MOCK_STATE/dispatch-response"
expect_exit 2 --apply --dispatch kumwe/conversion
! grep -q 'Release workflow queued:' "$test_tmp/stdout"
passed 'mismatched dispatch response cannot report an unrelated workflow as recovery success'

reset_fixture
echo '{"endpoint":"repos/kumwe/conversion/immutable-releases","status":403}' > "$MOCK_STATE/failure"
expect_exit 2 --apply --dispatch kumwe/conversion kumwe/producer
jq -se '[.[] | select(.endpoint | endswith("/dispatches"))] == [
  {method:"POST",endpoint:"repos/kumwe/producer/actions/workflows/release-on-record.yml/dispatches",
    body:{ref:"main"}}]
  and all(.[] | select(.endpoint | startswith("repos/kumwe/conversion")); .method == "GET")' \
  "$MOCK_STATE/calls" >/dev/null
passed 'fleet recovery continues after a configuration failure and dispatches only verified repositories'

printf 'Repository configuration fixtures passed: %s cases.\n' "$tests"
