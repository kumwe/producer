# The Producer charter

**Kumwe Producer** is the PHP realization layer for [Kumwe Studio](https://github.com/kumwe/studio).
Studio is where a composition is designed; Producer is what makes it real inside a PHP application:
it validates what arrives on the wire, renders published compositions into semantic HTML, and
generates the stylesheet a design's tokens imply — while the host application keeps every shred of
authority.

This charter is normative for the repository. A change that contradicts it is a defect, whatever
tests it passes.

## What Producer is

1. **A contract implementation, not a framework.** Producer implements the published Studio
   contract — the schemas, operations, error taxonomy, canonical serialization, and renderer
   conformance vectors — at one exact pinned Studio release. Its public surface follows the
   contract; it invents no protocol of its own.
2. **Host-neutral.** Any PHP application — whatever its framework, ORM, or template engine — can
   adopt Producer. Nothing in this repository may import, assume, or special-case a particular
   host, including Kumwe App. Kumwe App is Producer's first consumer, never its owner.
3. **Proven, not trusted.** Every capability is demonstrated against the vendored, digest-verified
   Studio corpus. A renderer that is not exercised by the conformance vectors is not claimed.

## What Producer must never contain

1. **No authority.** Producer never decides who may do what. Authentication, authorization,
   capability checks, and policy belong to the host; Producer's interfaces hand the host every
   decision and fail closed when the host refuses.
2. **No storage.** Producer never owns a database, a table, or a file store. It defines canonical
   wire outcomes and validated artifacts; the host owns persistence, encryption, redaction,
   protected capability handles, revisions, replay rehydration, and audit.
3. **No Node.js.** Producer is pure PHP. It never compiles, bundles, or executes JavaScript or
   TypeScript. Prebuilt browser assets are Studio release artifacts that Producer pins and the
   host serves.
4. **No code generation at render time.** A composition is data. Rendering assembles bounded,
   escaped HTML and static CSS from that data; it never evaluates stored markup, stored styles, or
   stored scripts, and it never emits an inline script. Progressive behaviour on a published page
   comes only from Studio's prebuilt, versioned enhancement runtime, referenced — never generated.
5. **No floating dependency on a draft contract.** The Studio pin is exact. A contract change
   reaches this repository only as a deliberate re-pin with its own review and evidence.
6. **No runtime Composer dependencies.** The library runs on PHP with `ext-json` and
   `ext-mbstring` alone, so a host adopts a contract implementation, not a dependency tree.

## The boundary in one line

**Studio designs it. Producer makes it real. The host owns it.**

## Relationships

- **With Studio** ([`kumwe/studio`](https://github.com/kumwe/studio)): Producer vendors and
  digest-verifies the published contract corpus at the pinned release recorded in
  [`resources/studio-contract/PIN.json`](resources/studio-contract/PIN.json). Producer never
  paraphrases a canonical document and never weakens a schema to make PHP convenient. A need the
  contract cannot express is a finding raised in the Studio repository, not a local workaround.
- **With hosts** (Kumwe App first): the host implements Producer's port interfaces with its own
  authority and storage, embeds Producer's render results in its own templates, and serves
  Studio's prebuilt assets. The agreement is recorded in
  [`docs/host-agreement.md`](docs/host-agreement.md).

## Governance

Work is recorded in [`docs/roadmap.md`](docs/roadmap.md) while open and in
  [`CHANGELOG.md`](CHANGELOG.md) when delivered; a claim states only what the check lane proves on a
  clean clone. The check lane is `composer check`: lint, documentation, architecture and public
  API gates, contract proof, Composer autoload smoke, and the dependency-free test suite. Every
  commit passes it.
