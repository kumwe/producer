#!/usr/bin/env bash
# Fail closed on observed release-integrity metadata; no network or mutation occurs here.
set -euo pipefail

case "${1:-}" in
  protected)
    if [[ "$#" -ne 2 || "$2" != true ]]; then
      echo 'Release refused: main must be protected by an active branch rule or ruleset.' >&2
      exit 1
    fi
    ;;
  published)
    if [[ "$#" -ne 2 || ! "$2" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; then
      echo 'Release verification requires one exact stable SemVer version.' >&2
      exit 1
    fi
    if ! jq -es --arg tag "v$2" '
      length == 1 and (.[0] | type == "object" and .tag_name == $tag and .draft == false and .prerelease == false
      and .immutable == true and (.published_at | type == "string" and length > 0))
    ' >/dev/null; then
      echo 'Release refused: exact version must be published, stable and immutable.' >&2
      exit 1
    fi
    ;;
  *)
    echo 'Usage: check-release-integrity.sh protected true | published VERSION < release.json' >&2
    exit 2
    ;;
esac
