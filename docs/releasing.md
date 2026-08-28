# Releasing Producer

Producer versions independently under semantic versioning; alignment with Studio travels through
the pin, never through matching version numbers.

1. Land the work on `main` with its `CHANGELOG.md` section for the next version.
2. Tag the release commit `vX.Y.Z` and push the tag.
3. The release workflow re-proves the tagged commit (lint, contract verification, suite), refuses
   a version the changelog does not record, and publishes the GitHub release naming the exact
   Studio pin it implements.
4. Packagist follows tags through its GitHub integration — submit `kumwe/producer` once at
   packagist.org and every later release appears without a credential in this repository.

Version policy:

- **Patch** — behaviour fixes at the same Studio pin.
- **Minor** — new capability, or a Studio re-pin that stays wire-compatible for hosts.
- **Major** — a change a host must act on, including a Studio re-pin that moves the wire.
- While Studio's contract is pre-release, Producer stays `0.x` and hosts pin exactly.
