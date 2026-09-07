#!/usr/bin/env bash
# Read the newest release heading from standard input, optionally after Unreleased.
# An empty changelog or Unreleased-only changelog emits no version. Malformed records
# fail closed instead of falling through to an older release. Used for both the
# pushed changelog and an existing tag's immutable changelog.
set -euo pipefail

mapfile -t headings < <(sed -n '/^## /p')
heading="${headings[0]:-}"
if [[ "$heading" == '## Unreleased' || "$heading" == '## [Unreleased]' ]]; then
  heading="${headings[1]:-}"
fi
if [[ -z "$heading" ]]; then
  exit 0
fi

pattern='^## (\[([0-9]+\.[0-9]+\.[0-9]+)\]|([0-9]+\.[0-9]+\.[0-9]+))( .*)?$'
if [[ ! "$heading" =~ $pattern ]]; then
  echo "Invalid newest changelog release record: $heading" >&2
  exit 1
fi
version="${BASH_REMATCH[2]:-${BASH_REMATCH[3]}}"
IFS=. read -r major minor patch <<< "$version"
for component in "$major" "$minor" "$patch"; do
  if [[ "$component" != 0 && "$component" == 0* ]]; then
    echo "A semantic version cannot contain a leading zero: $version" >&2
    exit 1
  fi
done
echo "$version"
