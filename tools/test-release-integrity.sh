#!/usr/bin/env bash
# Exercise real integrity predicates with isolated API-response fixtures, never online state.
set -euo pipefail
tool="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)/check-release-integrity.sh"
checks=0

accept() {
  bash "$tool" "$@" >/dev/null
  checks=$((checks + 1))
}

refuse() {
  if bash "$tool" "$@" >/dev/null 2>&1; then
    echo 'Integrity gate accepted an invalid release fixture.' >&2
    exit 1
  fi
  checks=$((checks + 1))
}

accept protected true
for state in false '' TRUE 1; do
  refuse protected "$state"
done
refuse protected
refuse protected true extra
refuse unknown
payload='{"tag_name":"v0.1.1","draft":false,"prerelease":false,"immutable":true,
"published_at":"2026-09-07T00:00:00Z"}'
accept published 0.1.1 <<< "$payload"
refuse published 0.1.0 <<< "$payload"
refuse published 00.1.1 <<< "$payload"
refuse published 0.1.1 extra <<< "$payload"
for change in '.draft = true' '.immutable = false' '.prerelease = true' '.published_at = null' \
  '.published_at = ""' '.immutable = "true"' 'del(.immutable)' 'del(.draft)' 'del(.published_at)' \
  '.tag_name = "v0.1.0"'; do
  refuse published 0.1.1 <<< "$(jq -c "$change" <<< "$payload")"
done
for malformed in '' '{}' '[]' 'null' 'true' 'not-json'; do
  refuse published 0.1.1 <<< "$malformed"
done
refuse published 0.1.1 <<< "$payload $payload"
refuse published 0.1.1 <<< "{} $payload"
refuse published 0.1.1 <<< "$payload {}"
echo "Release integrity gate passed: $checks isolated fixtures."
