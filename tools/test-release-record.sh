#!/usr/bin/env bash
# Exercise the parser used at both publication and existing-tag verification.
set -euo pipefail
tool="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)/read-release-record.sh"

assert_record() {
  local expected="$1" input="$2" actual
  actual="$(bash "$tool" <<< "$input")"
  if [[ "$actual" != "$expected" ]]; then
    echo "Expected release '$expected'; received '$actual'." >&2
    exit 1
  fi
}

assert_invalid() {
  if bash "$tool" <<< "$1" >/dev/null 2>&1; then
    echo "Accepted a malformed changelog release record." >&2
    exit 1
  fi
}

assert_record '0.1.1' $'# Changelog\n\n## 0.1.1\n\n## 0.1.0'
assert_record '0.1.1' $'## [0.1.1] - 2026-09-07\n## 0.1.0'
assert_record '0.1.1' $'## Unreleased\nFuture work\n## 0.1.1\n## 0.1.0'
assert_record '0.1.1' $'## [Unreleased]\nFuture work\n## [0.1.1] - 2026-09-07'
assert_record '' '# Changelog'
assert_record '' '## Unreleased'
assert_invalid $'## broken\n## 0.1.0'
assert_invalid $'## Unreleased\n## broken\n## 0.1.0'
assert_invalid '## [0.1.1'
assert_invalid '## 0.1.1]'
assert_invalid '## 00.1.1'
assert_invalid '## Unreleased '
echo 'Release record parser passed: 12 cases.'
