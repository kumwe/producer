#!/usr/bin/env bash
# No network or repository changes: every request must be a read served by gh.
set -euo pipefail
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
task_tmp="$(mktemp -d)"
trap 'rm -rf -- "$task_tmp"' EXIT
mkdir -p "$task_tmp/bin" "$task_tmp/state"
export MOCK_STATE="$task_tmp/state"
export PATH="$task_tmp/bin:$PATH"
export GITHUB_REPOSITORY=kumwe/conversion
export DEFAULT_BRANCH=main

cat > "$task_tmp/bin/gh" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
[[ "$1" == api ]] || exit 90
shift
method='' endpoint=''
while (($#)); do
  case "$1" in
    --method) method="$2"; shift 2 ;;
    --hostname|-H) shift 2 ;;
    --include) shift ;;
    *) endpoint="$1"; shift ;;
  esac
done
[[ "$method" == GET ]] || exit 91
echo "$endpoint" >> "$MOCK_STATE/calls"
if [[ -f "$MOCK_STATE/status" ]]; then
  status="$(cat "$MOCK_STATE/status")"
  printf 'HTTP/2.0 %s Fixture\r\n\r\n{"message":"Denied"}\n' "$status"
  exit 1
fi
branch_path="$(jq -rn --arg branch "$DEFAULT_BRANCH" '$branch | @uri')"
case "$endpoint" in
  "repos/$GITHUB_REPOSITORY/branches/$branch_path") response="$MOCK_STATE/branch" ;;
  "repos/$GITHUB_REPOSITORY/rules/branches/$branch_path?per_page=100&page=1") response="$MOCK_STATE/rules" ;;
  "repos/$GITHUB_REPOSITORY/rules/branches/$branch_path?per_page=100&page=2") response="$MOCK_STATE/rules-2" ;;
  *) echo "Unexpected request: $endpoint" >&2; exit 92 ;;
esac
printf 'HTTP/2.0 200 OK\r\nContent-Type: application/json\r\n\r\n'
cat "$response"
MOCK
chmod +x "$task_tmp/bin/gh"

reset_fixture() {
  rm -f "$MOCK_STATE"/*
  DEFAULT_BRANCH=main
  jq -n --arg branch "$DEFAULT_BRANCH" '{name:$branch,protected:true}' > "$MOCK_STATE/branch"
  echo '[{"type":"required_status_checks","parameters":{"required_status_checks":
    [{"context":"Package gate","integration_id":15368}]}}]' > "$MOCK_STATE/rules"
  : > "$MOCK_STATE/calls"
}
tests=0
expect() {
  local expected="$1" label="$2" result=0
  bash "$script_dir/check-release-prerequisites.sh" > "$task_tmp/stdout" 2> "$task_tmp/stderr" || result=$?
  if ((result != expected)); then
    cat "$task_tmp/stdout" "$task_tmp/stderr" >&2
    echo "Expected $expected, received $result: $label" >&2
    exit 1
  fi
  tests=$((tests + 1))
  printf 'ok %s - %s\n' "$tests" "$label"
}

reset_fixture
expect 0 'protected default branch with Actions Package gate passes'
[[ "$(wc -l < "$MOCK_STATE/calls")" -eq 2 ]]

for state in false '"true"' null; do
  reset_fixture
  jq --argjson value "$state" '.protected = $value' "$MOCK_STATE/branch" > "$MOCK_STATE/next"
  mv "$MOCK_STATE/next" "$MOCK_STATE/branch"
  expect 1 "protection must be boolean true: $state"
done

reset_fixture
echo '{"name":"wrong","protected":true}' > "$MOCK_STATE/branch"
expect 1 'a different protected branch does not satisfy the check'

for branch in master stable/release; do
  reset_fixture
  DEFAULT_BRANCH="$branch"
  jq -n --arg branch "$DEFAULT_BRANCH" '{name:$branch,protected:true}' > "$MOCK_STATE/branch"
  expect 0 "default branch is dynamic and URL encoded: $branch"
done

for status in 403 404 503; do
  reset_fixture
  echo "$status" > "$MOCK_STATE/status"
  expect 1 "HTTP $status cannot pass prerequisites"
done

for response in '{broken' '{}' 'null' '[] []'; do
  reset_fixture
  echo "$response" > "$MOCK_STATE/branch"
  expect 1 "malformed branch metadata fails: $response"
done

for response in '{broken' '{}' 'null' '[]' '[{"type":"required_status_checks","parameters":{}}]'; do
  reset_fixture
  echo "$response" > "$MOCK_STATE/rules"
  expect 1 "missing or malformed effective gate fails: $response"
done

reset_fixture
sed -i 's/15368/123/' "$MOCK_STATE/rules"
expect 1 'a Package gate supplied by another integration is rejected'

reset_fixture
sed -i 's/Package gate/PHP 8.5/' "$MOCK_STATE/rules"
expect 1 'an obsolete PHP-only gate does not satisfy the standard'

reset_fixture
cp "$MOCK_STATE/rules" "$MOCK_STATE/rules-2"
jq -n '[range(100) | {type:"deletion"}]' > "$MOCK_STATE/rules"
expect 0 'effective branch rules are paginated'
[[ "$(wc -l < "$MOCK_STATE/calls")" -eq 3 ]]

reset_fixture
DEFAULT_BRANCH='../unsafe'
expect 1 'invalid branch fails before API requests'
[[ ! -s "$MOCK_STATE/calls" ]]

printf 'Release prerequisite fixtures passed: %s cases.\n' "$tests"
