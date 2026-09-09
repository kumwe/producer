# Producer roadmap

Forward work only; delivered work moves to [`CHANGELOG.md`](../CHANGELOG.md) in the change that
completes it. A step is claimed only when `php tools/check.php` proves it on a clean clone.

## Remaining work

The implemented runtime and published Studio resource boundary are documented in
[`README.md`](../README.md) and [`CHANGELOG.md`](../CHANGELOG.md). The latest observed package
release is [`v0.2.1`](https://github.com/kumwe/producer/releases/tag/v0.2.1), at commit
`e8b2def866b95981b8e7ac521c16420a0f7955c8`. The completed P-1 through P-10 and P-12 work is
removed from this forward-work list. Source publication does not prove a consuming App's
deployment or integration gates.

| # | Step | Proof |
| --- | --- | --- |
| P-11 | Twig bridge: embed the render result (fragment, stylesheet reference, enhancement flag, preload hints) as a thin extension | bridge suite |

Deferred by design: anything the charter forbids, and any shape outside the published pin —
Producer implements the exact provenance-backed Studio contract only.
