#!/usr/bin/env bash
# Validate observed release metadata without network access or mutation.
set -euo pipefail

refuse_protection() {
  echo 'Release refused: the default branch must have active branch protection or an active ruleset.' >&2
  echo 'Run tools/configure-release-repositories.sh with an administrator login; see docs/releasing.md.' >&2
  exit 1
}

case "${1:-}" in
  protected)
    [[ "$#" -eq 2 && "$2" == true ]] || refuse_protection
    ;;
  source)
    # Compatibility with previously shipped fixtures; new callers pass DEFAULT_BRANCH.
    [[ "$#" -eq 3 && "$2" == "refs/heads/${DEFAULT_BRANCH:-main}" && "$3" == true ]] || refuse_protection
    ;;
  branch|protected-branch)
    [[ "$#" -le 2 ]] || refuse_protection
    branch="${2:-${DEFAULT_BRANCH:-main}}"
    [[ -n "$branch" ]] || refuse_protection
    if ! jq -es --arg branch "$branch" '
      length == 1 and (.[0] | type == "object" and .name == $branch and .protected == true)
    ' >/dev/null; then
      refuse_protection
    fi
    ;;
  published)
    if [[ "$#" -ne 2 || ! "$2" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; then
      echo 'Release verification requires one exact stable SemVer version.' >&2
      exit 1
    fi
    if ! jq -es --arg tag "v$2" '
      length == 1 and (.[0] | type == "object" and .tag_name == $tag and .draft == false
      and .prerelease == false and .immutable == true
      and (.published_at | type == "string" and length > 0))
    ' >/dev/null; then
      echo 'Release refused: exact version must be published, stable and immutable.' >&2
      exit 1
    fi
    ;;
  *)
    echo 'Usage: check-release-integrity.sh protected true | source REF true' >&2
    echo '       check-release-integrity.sh branch [BRANCH] < branch.json' >&2
    echo '       check-release-integrity.sh protected-branch BRANCH < branch.json' >&2
    echo '       check-release-integrity.sh published VERSION < release.json' >&2
    exit 2
    ;;
esac
