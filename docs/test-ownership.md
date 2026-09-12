# Package test ownership

The package's `tests/ownership.json` maps every published type to the actual package runner's discovered behavior and boundary tests, and records the package-owned conformance corpus. The quality gate validates the complete API inventory, test names and evidence paths. Nine negative fixtures prove that stale, missing or unowned evidence fails. New exports cannot land without an ownership entry. The runner refuses empty suites and empty cases in both execution and discovery modes.

Evidence references identify responsibility; they are not a claim of 100% line, branch or input coverage. Ports with no runtime implementation own their signatures and vocabulary here; concrete host implementations retain their execution tests. Package tests use neutral fixtures and adapters, and never bootstrap Kumwe App.

Core owns host composition, authority, persistence, delivery, lifecycle and recovery tests. The release record and consumer inventory preserve the examined baseline and exact test paths. Before changing ownership, identify any pure package tests to remove or mixed tests to split explicitly. A dependency update does not authorize deleting host acceptance coverage.

Producer retains its pinned Studio canonical, schema, renderer and error corpora; App retains real host authority, storage and browser integration.
