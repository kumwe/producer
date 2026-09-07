# Package test ownership

The package's `tests/ownership.json` maps every published type to the actual package runner's discovered behavior and boundary tests, and records the package-owned conformance corpus. The quality gate validates the complete API inventory, test names and evidence paths. Eight negative fixtures prove that stale, missing or unowned evidence fails. New exports cannot land without an ownership entry. The runner refuses empty suites and empty cases in both execution and discovery modes.

Evidence references identify responsibility; they are not a claim of 100% line, branch or input coverage. Ports with no runtime implementation own their signatures and vocabulary here; concrete host implementations retain their execution tests. Package tests use neutral fixtures and adapters, and never bootstrap Kumwe App.

The legacy extraction is already adopted in App at baseline 960ce8ec00cf724a7cae03e5ba09c4852c9ab54e. The current App tests inspected exercise host composition, authority, persistence, delivery, lifecycle or recovery. No whole current App test file is identified for removal by this patch. Future extraction handoffs must list exact pure tests to remove or mixed tests to split on adoption. A dependency bump alone is not authorization to delete host acceptance tests.

Producer retains its pinned Studio canonical, schema, renderer and error corpora; App retains real host authority, storage and browser integration.
