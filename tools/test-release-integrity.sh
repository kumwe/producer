#!/usr/bin/env bash
# Offline metadata fixtures, including compatibility with earlier release gates.
set -euo pipefail
tool="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)/check-release-integrity.sh"
checks=0
accept() { bash "$tool" "$@" >/dev/null; checks=$((checks + 1)); }
refuse() {
  if bash "$tool" "$@" >/dev/null 2>&1; then
    echo 'Integrity gate accepted an invalid release fixture.' >&2
    exit 1
  fi
  checks=$((checks + 1))
}
accept protected true
for state in false '' TRUE 1; do refuse protected "$state"; done
refuse protected
refuse protected true extra
refuse unknown
accept source refs/heads/main true
for ref in refs/heads/feature refs/tags/v0.1.1 refs/pull/1/merge main ''; do refuse source "$ref" true; done
for state in false '' TRUE 1; do refuse source refs/heads/main "$state"; done
refuse source
refuse source refs/heads/main
refuse source refs/heads/main true extra
DEFAULT_BRANCH=master accept source refs/heads/master true
DEFAULT_BRANCH=master refuse source refs/heads/main true

branch='{"name":"main","protected":true}'
for mode in branch protected-branch; do
  accept "$mode" main <<< "$branch"
  refuse "$mode" other <<< "$branch"
  refuse "$mode" main extra <<< "$branch"
  for change in '.name = "other"' '.protected = false' '.protected = "true"' '.protected = 1' \
    'del(.name)' 'del(.protected)'; do
    refuse "$mode" main <<< "$(jq -c "$change" <<< "$branch")"
  done
  for malformed in '' '{}' '[]' 'null' 'true' 'not-json'; do refuse "$mode" main <<< "$malformed"; done
  refuse "$mode" main <<< "$branch $branch"
  refuse "$mode" main <<< "{} $branch"
  refuse "$mode" main <<< "$branch {}"
done
accept branch <<< "$branch"
accept protected-branch master <<< '{"name":"master","protected":true}'
accept protected-branch stable/release <<< '{"name":"stable/release","protected":true}'
DEFAULT_BRANCH=master accept branch <<< '{"name":"master","protected":true}'
if (set -o pipefail; { printf '%s\n' "$branch"; exit 1; } | bash "$tool" branch); then
  echo 'Integrity gate ignored an upstream API failure.' >&2
  exit 1
fi
checks=$((checks + 1))

payload='{"tag_name":"v0.1.1","draft":false,"prerelease":false,"immutable":true,
"published_at":"2026-09-07T00:00:00Z"}'
accept published 0.1.1 <<< "$payload"
for version in 0.1.0 00.1.1 0.01.1 0.1.01 v0.1.1 0.1.1-rc1 ''; do refuse published "$version" <<< "$payload"; done
refuse published 0.1.1 extra <<< "$payload"
for change in '.draft = true' '.immutable = false' '.prerelease = true' '.published_at = null' \
  '.published_at = ""' '.immutable = "true"' 'del(.immutable)' 'del(.draft)' 'del(.published_at)' \
  '.tag_name = "v0.1.0"'; do
  refuse published 0.1.1 <<< "$(jq -c "$change" <<< "$payload")"
done
for malformed in '' '{}' '[]' 'null' 'true' 'not-json'; do refuse published 0.1.1 <<< "$malformed"; done
refuse published 0.1.1 <<< "$payload $payload"
refuse published 0.1.1 <<< "{} $payload"
refuse published 0.1.1 <<< "$payload {}"
echo "Release integrity gate passed: $checks isolated fixtures."
