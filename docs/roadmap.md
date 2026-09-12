# Producer development priorities

The current runtime is documented in the [README](../README.md), [public API](public-api.md) and
[changelog](../CHANGELOG.md). Development must preserve the [host agreement](host-agreement.md)
and the exact published Studio contract.

## Optional Twig integration

A thin Twig extension may embed the render result: fragment, stylesheet reference, enhancement
flag and preload hints. It must preserve host-owned templates and authority, and include a bridge
suite proving the published render contract. This bridge is not part of the current API.

Changes outside the pinned Studio contract must first be expressed and released by Studio, then
adopted through the [pin and release policy](releasing.md). Source CI does not qualify a host deployment.
