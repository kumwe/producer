# Engineering standard

This document says out loud what "good" means in this repository, so quality is a stated,
checkable expectation rather than a hope. It binds every contribution, human or agent. The
[charter](../CHARTER.md) says what Producer is; this says how it is built.

## Architecture

Producer is a layered library with one dependency direction. A layer may use the layers above it
in this table and never the ones below:

| Layer | Namespace | Owns | Must never know about |
| --- | --- | --- | --- |
| Canonical | `Kumwe\Producer\Canonical` | Canonical JSON bytes, digests | HTTP, rendering, hosts |
| Schema | `Kumwe\Producer\Schema` | The schema-property profile: admission and instance validation | HTTP, rendering, hosts |
| Error | `Kumwe\Producer\Error` | The closed host-error taxonomy | rendering, hosts |
| Wire | `Kumwe\Producer\Wire` | Envelopes, the operation registry, dispatch, port interfaces | rendering internals, any concrete host |
| Render | `Kumwe\Producer\Render` | Composition to semantic HTML, escaping, fallbacks | HTTP, storage, any concrete host |
| Css | `Kumwe\Producer\Css` | Design tokens and layout vocabulary to static stylesheets | HTTP, storage |

Rules that keep the layers honest:

1. **The contract is the API.** Public shapes follow the vendored Studio schemas; nothing invents
   a shape the contract does not define. When the contract is ambiguous, the conservative reading
   wins and the choice is recorded in a doc comment where the code makes it.
2. **All authority is injected.** Producer never decides, stores, or fetches. Every effect crosses
   a small PHP interface the host implements. There is no service locator, no global state, no
   static mutable anything.
3. **Fail closed, with the taxonomy.** Every refusal is a typed error carrying a stable code from
   the closed taxonomy. Free-text matching is forbidden; message strings are for humans only and
   never disclose internals.
4. **Determinism.** Same input, same Producer release: identical output bytes. No clocks, no
   randomness, no locale-dependent formatting anywhere in the library. Anything nondeterministic
   belongs to the host, behind an interface.
5. **Bounded before expensive.** Size and depth limits are enforced before sorting, hashing, or
   walking attacker-influenced data, so hostile input cannot amplify work.

## Code

- `declare(strict_types=1)` in every file; `final` classes by default; constructor injection only;
  no inheritance where composition serves; small classes named for their one responsibility.
- Every documentable member carries a doc block ending in `@since`, enforced by
  `php tools/check-docblocks.php` in `composer check` and CI — an undocumented member fails the
  build. A block states the member's contract — what it accepts,
  what it guarantees, what it throws and when — not a restatement of its name. `@since` records
  the version that introduced the member and is never rewritten.
- No runtime Composer dependencies, ever (`php` + `ext-json` + `ext-mbstring` only). This is a
  contract implementation a host adopts, not a dependency tree.
- Escaping is centralized. Exactly one path turns untrusted text into markup, attribute, or URL
  output; a renderer that concatenates a stored value into HTML directly is a defect wherever the
  value came from.

## Testing

The suite exists to prove intended outcomes, and only that. The standard for every test:

1. **A test asserts an observable contract, not an implementation detail.** It proves what the
   class promises — the canonical bytes, the refusal code, the rendered markup, the diagnostic
   pointer — never that a private method was called or an internal array has a shape.
2. **The conformance corpora are the spine.** Anything the vendored Studio vectors can prove is
   proven by replaying them, because those assertions are shared with every other conforming
   runtime. Local tests cover what the corpus cannot: hostile input, boundary values, refusal
   paths, determinism (two runs, identical bytes), and the subtleties recorded in the
   [porting guide](porting-guide.md).
3. **Negative paths are first-class.** Every typed refusal a class can produce has a test that
   provokes it and asserts its stable code. A class whose failure modes are untested is untested.
4. **No frivolous tests.** A test that cannot fail for a reason a user would care about — testing
   a getter returns what the constructor took, restating the code in mock expectations, asserting
   a class exists — must not be written. Coverage is a consequence of testing outcomes, never a
   goal pursued for its own number.
5. **Dependency-free and deterministic.** Tests run with `php tests/run.php` on a clean clone with
   no composer install, touch no network and no clock, and pass in any order.

## Documentation

- Every document states behaviour that exists; plans live in [`docs/roadmap.md`](roadmap.md) and
  nowhere else. A claim the check lane cannot back is a defect in the document.
- Wrap prose at roughly 100 columns; sentence-case headings; link with relative paths; write for
  the reader who arrives with no context, because the next implementer may be an agent with
  nothing but this repository.

## The check lane

`composer check` is the whole standard, executable: lint, contract digest verification, suite.
Every commit passes it; CI runs it on PHP 8.1 through 8.4; a release re-proves it on the tagged
commit. There is no path to publication that skips it.
