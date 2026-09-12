---
schema: kumwe-package-release-record/v1
artifact_kind: framework_php
migration_id: KUMWE-MIG-2026-032
change_set: KUMWE-CS-2026-032
source:
  app:
    repository: https://github.com/kumwe/app
    baseline_commit: 24ecf956423c18933e824b43cea1bfb9127a79a9
    examined_paths:
      - composer.json
      - composer.lock
      - docs/architecture/capability-index.md
      - src/Administrator/Http/Handler/AdministratorStudioHostHandler.php
      - src/Administrator/Http/Handler/AdministratorStudioPreviewDocumentHandler.php
      - src/Administrator/Http/Handler/AdministratorStudioSessionHandler.php
      - src/Extension/Contribution/CoreStudioCompositionContributions.php
      - src/Extension/Contribution/StudioPreviewRendererContribution.php
      - src/Kernel/ContainerFactory.php
      - src/Studio/Application/Composition/CanonicalStudioPublishedContentRenderer.php
      - src/Studio/Application/Composition/StudioCompositionContributionCatalog.php
      - src/Studio/Application/Composition/StudioPublishedCompositionGuard.php
      - src/Studio/Application/Composition/StudioPublishedContentRenderer.php
      - src/Studio/Application/Host/StudioArtifactAdmission.php
      - src/Studio/Application/Host/StudioArtifactHostPort.php
      - src/Studio/Application/Host/StudioArtifactPublicationGuard.php
      - src/Studio/Application/Host/StudioLocalizationHostPort.php
      - src/Studio/Application/Host/StudioModelHostPort.php
      - src/Studio/Application/Host/StudioMutationOutcomeCodec.php
      - src/Studio/Application/Host/StudioMutationReplayRepository.php
      - src/Studio/Application/Host/StudioPermissionHostPort.php
      - src/Studio/Application/Host/StudioProducerError.php
      - src/Studio/Application/Host/StudioProducerHost.php
      - src/Studio/Application/Host/StudioProducerMutationBoundary.php
      - src/Studio/Application/Host/StudioProducerRequestAuthority.php
      - src/Studio/Application/Host/StudioRecoveryHostPort.php
      - src/Studio/Application/Host/StudioResourceHostPort.php
      - src/Studio/Application/Host/StudioTelemetryHostPort.php
      - src/Studio/Application/Media/StudioMediaHostPort.php
      - src/Studio/Application/Preview/ContributedStudioPreviewBlock.php
      - src/Studio/Application/Preview/StudioPreviewBindingResolver.php
      - src/Studio/Application/Preview/StudioPreviewBindingValues.php
      - src/Studio/Application/Preview/StudioPreviewHostPort.php
      - src/Studio/Application/Projection/ContentStudioProjector.php
      - src/Studio/Application/Rendering/FragmentStudioPreviewBlockRenderer.php
      - src/Studio/Application/Rendering/StudioBlockRendererRuntime.php
      - src/Studio/Application/Rendering/StudioContentFieldBlockRenderer.php
      - src/Studio/Application/Rendering/StudioLayoutBlockRenderer.php
      - src/Studio/Application/Rendering/StudioRenderResultAdmission.php
      - src/Studio/Domain/Artifact/StoredStudioArtifact.php
      - src/Studio/Domain/Preview/StudioPreviewDraft.php
      - src/Studio/Domain/Preview/StudioPreviewIdentity.php
      - src/Studio/Domain/Projection/EntryCompositionOverrides.php
      - src/Studio/Infrastructure/Host/SodiumStudioMutationOutcomeCodec.php
      - src/Studio/Infrastructure/Persistence/DoctrineContentProjectionBindingRepository.php
      - src/Studio/Infrastructure/Persistence/DoctrineStudioHostStorage.php
      - src/Studio/Infrastructure/Persistence/DoctrineStudioPreviewDraftSource.php
      - src/Studio/Infrastructure/Persistence/DoctrineStudioPreviewRepository.php
      - src/Studio/Infrastructure/Release/PinnedStudioContextualAuthoringAvailability.php
      - src/Studio/Presentation/Preview/CanonicalStudioPreviewRenderer.php
      - tests/Architecture/StudioMediaBoundaryTest.php
      - tests/Integration/Studio/ExtensionStudioPreviewRendererIntegrationTest.php
      - tests/Integration/Studio/StudioArtifactRecoveryPersistenceTest.php
      - tests/Integration/Studio/StudioArtifactRecoveryProducerIntegrationTest.php
      - tests/Integration/Studio/StudioArtifactRecoveryVectorReplayIntegrationTest.php
      - tests/Integration/Studio/StudioPreviewDraftSourceTest.php
      - tests/Integration/Studio/StudioPreviewPersistenceTest.php
      - tests/Support/StudioProducerRequest.php
      - tests/Unit/Administrator/Http/Handler/AdministratorStudioHostHandlerTest.php
      - tests/Unit/Extension/Contribution/StudioPreviewRendererContributionTest.php
      - tests/Unit/Http/Handler/HomePageHandlerTest.php
      - tests/Unit/Http/Handler/PublishedContentHandlerTest.php
      - tests/Unit/Site/Application/PublicPageLocatorTest.php
      - tests/Unit/Studio/Application/Composition/StudioCompositionContributionCatalogTest.php
      - tests/Unit/Studio/Application/Composition/StudioPublishedContentRendererTest.php
      - tests/Unit/Studio/Application/Host/StudioArtifactAdmissionTest.php
      - tests/Unit/Studio/Application/Host/StudioArtifactHostPortTest.php
      - tests/Unit/Studio/Application/Host/StudioLocalizationTelemetryHostPortTest.php
      - tests/Unit/Studio/Application/Host/StudioModelHostPortTest.php
      - tests/Unit/Studio/Application/Host/StudioPermissionHostPortTest.php
      - tests/Unit/Studio/Application/Host/StudioProducerErrorTest.php
      - tests/Unit/Studio/Application/Host/StudioProducerHostTest.php
      - tests/Unit/Studio/Application/Host/StudioProducerMutationBoundaryTest.php
      - tests/Unit/Studio/Application/Host/StudioProducerRequestAuthorityTest.php
      - tests/Unit/Studio/Application/Host/StudioRecoveryHostPortTest.php
      - tests/Unit/Studio/Application/Host/StudioResourceHostPortTest.php
      - tests/Unit/Studio/Application/Host/StudioTelemetryHostPortTest.php
      - tests/Unit/Studio/Application/Media/StudioMediaHostPortTest.php
      - tests/Unit/Studio/Application/Media/StudioMediaProducerPortTest.php
      - tests/Unit/Studio/Application/Preview/ContentStudioPreviewBindingSourceTest.php
      - tests/Unit/Studio/Application/Preview/StudioPreviewBindingRendererTest.php
      - tests/Unit/Studio/Application/Preview/StudioPreviewHostPortTest.php
      - tests/Unit/Studio/Application/Preview/StudioPreviewProducerPortTest.php
      - tests/Unit/Studio/Application/Projection/ContentStudioProjectorTest.php
      - tests/Unit/Studio/Application/Projection/StudioContentProjectionServiceTest.php
      - tests/Unit/Studio/Application/Rendering/FragmentStudioPreviewBlockRendererTest.php
      - tests/Unit/Studio/Application/Rendering/StudioBlockRendererRuntimeTest.php
      - tests/Unit/Studio/Application/Rendering/StudioContentFieldBlockRendererTest.php
      - tests/Unit/Studio/Application/Rendering/StudioLayoutBlockRendererTest.php
      - tests/Unit/Studio/Domain/Media/StudioMediaPolicyVectorTest.php
      - tests/Unit/Studio/Domain/Preview/StudioPreviewIdentityTest.php
      - tests/Unit/Studio/Domain/Projection/EntryCompositionOverridesTest.php
      - tests/Unit/Studio/Infrastructure/Host/SodiumStudioMutationOutcomeCodecTest.php
      - tests/Unit/Studio/Infrastructure/Release/PinnedStudioContextualAuthoringAvailabilityTest.php
    old_namespace_roots: []
    capability_index_sha256: 8fb2a8680bed6ac1456183bc9e48fe040194923b6d1b3331f04cea28bd5a9b2f
  semantic_inputs: []
  examined_dependencies:
    - PHP >=8.1, ext-json and ext-mbstring; no runtime Composer dependency.
    - Existing Producer0.2.1 at e8b2def866b95981b8e7ac521c16420a0f7955c8 owns the unchanged70-type API and Studio contract.
    - Studio0.1.0-beta.3 source42b149251a9f17a2ef8f32db0d9dd1ac2fcfec8a remains pinned in resources/studio-contract/PIN.json.
target:
  repository: https://github.com/kumwe/producer
  artifact_identity: kumwe/producer
  canonical_namespace_or_abi: Kumwe\Producer\
ownership:
  responsibility: Portable PHP implementation of the pinned Studio wire, schema, rendering and design-token contract.
  non_responsibilities:
    - Host authentication, authorization, persistence, revisions, lifecycle and delivery.
    - JavaScript compilation, dynamic script generation and runtime Composer dependency discovery.
    - The optional Twig bridge is not part of the current public API.
  allowed_dependency_ceiling:
    - PHP
    - ext-json
    - ext-mbstring
  implementation_owner: kumwe/producer
  next_consumer: kumwe/extension-sdk
  public_manifests:
    - path: resources/public-api/v1.json
      sha256: 5fe7efb49a29799fcc3c3bbdba9ba4138d63688669ba933a2be6996f13f12cc2
    - path: resources/capabilities/v1.json
      sha256: a7e4b69f5b5612272584b187ae9ee9afe34ea85499dca91b35260ab3b66ee8a5
    - path: resources/service-map/v1.json
      sha256: 7173160cd25ff40ff5002a8c5c63ae769ce8b8df6fcd1138f1d9e949acad7fd3
    - path: resources/public-api.json
      sha256: 4318cc2465787f56dec78f831ed7b9b45c8d0edd6f94657e789917e5ba4e22f7
    - path: resources/studio-contract/PIN.json
      sha256: 3c1b094e59c5bbdeb867b2f12df0b8a88a4855ae61003913444dda8222b4ee77
    - path: resources/studio-contract/protocol/schemas/manifest.json
      sha256: b7d41d6cbe71a770f433ddce12bdcd5582a2be0249191f4845ad5278079ff6e9
    - path: resources/studio-contract/testkit/corpus-manifest.json
      sha256: 3eea5d655bfe86d234c1dedb9c5b7055d173858541ec3c535e1faf8a204294d3
  intentionally_excluded:
    - Core application implementations retain authority, persistence and lifecycle ownership.
    - Studio owns its semantic contract; the exact imported API, corpus and browser asset pins remain authoritative.
framework_php:
  composer_package: kumwe/producer
  canonical_namespace: Kumwe\Producer\
  public_api_manifest: resources/public-api/v1.json
  capability_manifest: resources/capabilities/v1.json
  service_map: resources/service-map/v1.json
  extracted_symbols: []
  consumers:
    app_code:
      - src/Administrator/Http/Handler/AdministratorStudioHostHandler.php
      - src/Administrator/Http/Handler/AdministratorStudioPreviewDocumentHandler.php
      - src/Administrator/Http/Handler/AdministratorStudioSessionHandler.php
      - src/Extension/Contribution/CoreStudioCompositionContributions.php
      - src/Extension/Contribution/StudioPreviewRendererContribution.php
      - src/Kernel/ContainerFactory.php
      - src/Studio/Application/Composition/CanonicalStudioPublishedContentRenderer.php
      - src/Studio/Application/Composition/StudioCompositionContributionCatalog.php
      - src/Studio/Application/Composition/StudioPublishedCompositionGuard.php
      - src/Studio/Application/Composition/StudioPublishedContentRenderer.php
      - src/Studio/Application/Host/StudioArtifactAdmission.php
      - src/Studio/Application/Host/StudioArtifactHostPort.php
      - src/Studio/Application/Host/StudioArtifactPublicationGuard.php
      - src/Studio/Application/Host/StudioLocalizationHostPort.php
      - src/Studio/Application/Host/StudioModelHostPort.php
      - src/Studio/Application/Host/StudioMutationOutcomeCodec.php
      - src/Studio/Application/Host/StudioMutationReplayRepository.php
      - src/Studio/Application/Host/StudioPermissionHostPort.php
      - src/Studio/Application/Host/StudioProducerError.php
      - src/Studio/Application/Host/StudioProducerHost.php
      - src/Studio/Application/Host/StudioProducerMutationBoundary.php
      - src/Studio/Application/Host/StudioProducerRequestAuthority.php
      - src/Studio/Application/Host/StudioRecoveryHostPort.php
      - src/Studio/Application/Host/StudioResourceHostPort.php
      - src/Studio/Application/Host/StudioTelemetryHostPort.php
      - src/Studio/Application/Media/StudioMediaHostPort.php
      - src/Studio/Application/Preview/ContributedStudioPreviewBlock.php
      - src/Studio/Application/Preview/StudioPreviewBindingResolver.php
      - src/Studio/Application/Preview/StudioPreviewBindingValues.php
      - src/Studio/Application/Preview/StudioPreviewHostPort.php
      - src/Studio/Application/Projection/ContentStudioProjector.php
      - src/Studio/Application/Rendering/FragmentStudioPreviewBlockRenderer.php
      - src/Studio/Application/Rendering/StudioBlockRendererRuntime.php
      - src/Studio/Application/Rendering/StudioContentFieldBlockRenderer.php
      - src/Studio/Application/Rendering/StudioLayoutBlockRenderer.php
      - src/Studio/Application/Rendering/StudioRenderResultAdmission.php
      - src/Studio/Domain/Artifact/StoredStudioArtifact.php
      - src/Studio/Domain/Preview/StudioPreviewDraft.php
      - src/Studio/Domain/Preview/StudioPreviewIdentity.php
      - src/Studio/Domain/Projection/EntryCompositionOverrides.php
      - src/Studio/Infrastructure/Host/SodiumStudioMutationOutcomeCodec.php
      - src/Studio/Infrastructure/Persistence/DoctrineContentProjectionBindingRepository.php
      - src/Studio/Infrastructure/Persistence/DoctrineStudioHostStorage.php
      - src/Studio/Infrastructure/Persistence/DoctrineStudioPreviewDraftSource.php
      - src/Studio/Infrastructure/Persistence/DoctrineStudioPreviewRepository.php
      - src/Studio/Infrastructure/Release/PinnedStudioContextualAuthoringAvailability.php
      - src/Studio/Presentation/Preview/CanonicalStudioPreviewRenderer.php
    configuration_and_di:
      - composer.json
      - composer.lock
    reflection_and_string_references:
      - Re-scan current App dynamic/reflection references before the later Composer upgrade.
    fixtures_and_examples:
      - tests/Architecture/StudioMediaBoundaryTest.php
      - tests/Integration/Studio/ExtensionStudioPreviewRendererIntegrationTest.php
      - tests/Integration/Studio/StudioArtifactRecoveryPersistenceTest.php
      - tests/Integration/Studio/StudioArtifactRecoveryProducerIntegrationTest.php
      - tests/Integration/Studio/StudioArtifactRecoveryVectorReplayIntegrationTest.php
      - tests/Integration/Studio/StudioPreviewDraftSourceTest.php
      - tests/Integration/Studio/StudioPreviewPersistenceTest.php
      - tests/Support/StudioProducerRequest.php
      - tests/Unit/Administrator/Http/Handler/AdministratorStudioHostHandlerTest.php
      - tests/Unit/Extension/Contribution/StudioPreviewRendererContributionTest.php
      - tests/Unit/Http/Handler/HomePageHandlerTest.php
      - tests/Unit/Http/Handler/PublishedContentHandlerTest.php
      - tests/Unit/Site/Application/PublicPageLocatorTest.php
      - tests/Unit/Studio/Application/Composition/StudioCompositionContributionCatalogTest.php
      - tests/Unit/Studio/Application/Composition/StudioPublishedContentRendererTest.php
      - tests/Unit/Studio/Application/Host/StudioArtifactAdmissionTest.php
      - tests/Unit/Studio/Application/Host/StudioArtifactHostPortTest.php
      - tests/Unit/Studio/Application/Host/StudioLocalizationTelemetryHostPortTest.php
      - tests/Unit/Studio/Application/Host/StudioModelHostPortTest.php
      - tests/Unit/Studio/Application/Host/StudioPermissionHostPortTest.php
      - tests/Unit/Studio/Application/Host/StudioProducerErrorTest.php
      - tests/Unit/Studio/Application/Host/StudioProducerHostTest.php
      - tests/Unit/Studio/Application/Host/StudioProducerMutationBoundaryTest.php
      - tests/Unit/Studio/Application/Host/StudioProducerRequestAuthorityTest.php
      - tests/Unit/Studio/Application/Host/StudioRecoveryHostPortTest.php
      - tests/Unit/Studio/Application/Host/StudioResourceHostPortTest.php
      - tests/Unit/Studio/Application/Host/StudioTelemetryHostPortTest.php
      - tests/Unit/Studio/Application/Media/StudioMediaHostPortTest.php
      - tests/Unit/Studio/Application/Media/StudioMediaProducerPortTest.php
      - tests/Unit/Studio/Application/Preview/ContentStudioPreviewBindingSourceTest.php
      - tests/Unit/Studio/Application/Preview/StudioPreviewBindingRendererTest.php
      - tests/Unit/Studio/Application/Preview/StudioPreviewHostPortTest.php
      - tests/Unit/Studio/Application/Preview/StudioPreviewProducerPortTest.php
      - tests/Unit/Studio/Application/Projection/ContentStudioProjectorTest.php
      - tests/Unit/Studio/Application/Projection/StudioContentProjectionServiceTest.php
      - tests/Unit/Studio/Application/Rendering/FragmentStudioPreviewBlockRendererTest.php
      - tests/Unit/Studio/Application/Rendering/StudioBlockRendererRuntimeTest.php
      - tests/Unit/Studio/Application/Rendering/StudioContentFieldBlockRendererTest.php
      - tests/Unit/Studio/Application/Rendering/StudioLayoutBlockRendererTest.php
      - tests/Unit/Studio/Domain/Media/StudioMediaPolicyVectorTest.php
      - tests/Unit/Studio/Domain/Preview/StudioPreviewIdentityTest.php
      - tests/Unit/Studio/Domain/Projection/EntryCompositionOverridesTest.php
      - tests/Unit/Studio/Infrastructure/Host/SodiumStudioMutationOutcomeCodecTest.php
      - tests/Unit/Studio/Infrastructure/Release/PinnedStudioContextualAuthoringAvailabilityTest.php
    external:
      - kumwe/extension-sdk exact Producer dependency and generated extension toolchain.
  dependency_injection:
    mode: direct
    provider: null
    factories: []
    aliases: []
    service_lifetimes: []
    configuration_keys: []
    provider_absence_reason: The host explicitly constructs operations with host ports. Producer has no ambient container and its charter forbids runtime Composer dependencies.
native_cpp: null
php_extension: null
tests:
  moved_or_added:
    - tools/verify-governance.php
    - tools/verify-governed-manifests.php
    - tools/verify-archive.php
  remain_in_app_or_consumer:
    - tests/Architecture/StudioMediaBoundaryTest.php
    - tests/Integration/Studio/ExtensionStudioPreviewRendererIntegrationTest.php
    - tests/Integration/Studio/StudioArtifactRecoveryPersistenceTest.php
    - tests/Integration/Studio/StudioArtifactRecoveryProducerIntegrationTest.php
    - tests/Integration/Studio/StudioArtifactRecoveryVectorReplayIntegrationTest.php
    - tests/Integration/Studio/StudioPreviewDraftSourceTest.php
    - tests/Integration/Studio/StudioPreviewPersistenceTest.php
    - tests/Support/StudioProducerRequest.php
    - tests/Unit/Administrator/Http/Handler/AdministratorStudioHostHandlerTest.php
    - tests/Unit/Extension/Contribution/StudioPreviewRendererContributionTest.php
    - tests/Unit/Http/Handler/HomePageHandlerTest.php
    - tests/Unit/Http/Handler/PublishedContentHandlerTest.php
    - tests/Unit/Site/Application/PublicPageLocatorTest.php
    - tests/Unit/Studio/Application/Composition/StudioCompositionContributionCatalogTest.php
    - tests/Unit/Studio/Application/Composition/StudioPublishedContentRendererTest.php
    - tests/Unit/Studio/Application/Host/StudioArtifactAdmissionTest.php
    - tests/Unit/Studio/Application/Host/StudioArtifactHostPortTest.php
    - tests/Unit/Studio/Application/Host/StudioLocalizationTelemetryHostPortTest.php
    - tests/Unit/Studio/Application/Host/StudioModelHostPortTest.php
    - tests/Unit/Studio/Application/Host/StudioPermissionHostPortTest.php
    - tests/Unit/Studio/Application/Host/StudioProducerErrorTest.php
    - tests/Unit/Studio/Application/Host/StudioProducerHostTest.php
    - tests/Unit/Studio/Application/Host/StudioProducerMutationBoundaryTest.php
    - tests/Unit/Studio/Application/Host/StudioProducerRequestAuthorityTest.php
    - tests/Unit/Studio/Application/Host/StudioRecoveryHostPortTest.php
    - tests/Unit/Studio/Application/Host/StudioResourceHostPortTest.php
    - tests/Unit/Studio/Application/Host/StudioTelemetryHostPortTest.php
    - tests/Unit/Studio/Application/Media/StudioMediaHostPortTest.php
    - tests/Unit/Studio/Application/Media/StudioMediaProducerPortTest.php
    - tests/Unit/Studio/Application/Preview/ContentStudioPreviewBindingSourceTest.php
    - tests/Unit/Studio/Application/Preview/StudioPreviewBindingRendererTest.php
    - tests/Unit/Studio/Application/Preview/StudioPreviewHostPortTest.php
    - tests/Unit/Studio/Application/Preview/StudioPreviewProducerPortTest.php
    - tests/Unit/Studio/Application/Projection/ContentStudioProjectorTest.php
    - tests/Unit/Studio/Application/Projection/StudioContentProjectionServiceTest.php
    - tests/Unit/Studio/Application/Rendering/FragmentStudioPreviewBlockRendererTest.php
    - tests/Unit/Studio/Application/Rendering/StudioBlockRendererRuntimeTest.php
    - tests/Unit/Studio/Application/Rendering/StudioContentFieldBlockRendererTest.php
    - tests/Unit/Studio/Application/Rendering/StudioLayoutBlockRendererTest.php
    - tests/Unit/Studio/Domain/Media/StudioMediaPolicyVectorTest.php
    - tests/Unit/Studio/Domain/Preview/StudioPreviewIdentityTest.php
    - tests/Unit/Studio/Domain/Projection/EntryCompositionOverridesTest.php
    - tests/Unit/Studio/Infrastructure/Host/SodiumStudioMutationOutcomeCodecTest.php
    - tests/Unit/Studio/Infrastructure/Release/PinnedStudioContextualAuthoringAvailabilityTest.php
  split_tests: []
  prohibited_duplicates:
    - Do not copy Producer renderer, schema or Studio corpus implementations into the consuming App.
  corpora:
    - resources/studio-contract/testkit/corpus-manifest.json
documentation:
  charter: CHARTER.md
  readme: README.md
  public_api: docs/public-api.md
  architecture: docs/engineering-standard.md
  integration_or_consumer: docs/host-guide.md
  examples:
    - examples/minimal-host/README.md
  changelog_record: CHANGELOG.md / 0.3.0
release_expectations:
  version_policy: Use semantic versioning and the Studio pin compatibility policy in docs/releasing.md; preserve published releases.
  expected_artifact_types:
    - Composer package archive
    - GitHub source archive
  required_checks:
    - PHP8.1–8.5 complete reusable Package gate
    - Reflected legacy API and canonical manifest/schema agreement
    - Studio contract, browser license/SRI and renderer corpus verification
    - Exact archive boundary and fresh no-dev authoritative consumer
    - Independent post-publication source/archive/registry verification
  required_registry_or_installer: Composer
  required_external_attestation: true
governance:
  completion_claim: false
decisions:
  - Keep the complete 76-type public API and exact Studio source, browser asset and corpus pins.
  - Ship canonical discovery metadata, current public documentation and the release record in source and Composer archives.
  - The Deployment layer implements pinned browser location, schema-bound deployment emission and transport admission.
  - Host applications retain authorization, persistence, delivery and lifecycle responsibilities.
  - The optional Twig bridge remains a development objective.
blockers: []
consumer_contract:
  permitted_only_when:
    - Actual release and exact immutable source/archive/registry identities are independently verified.
    - Final complete Package gate passes on the merged release source.
    - The consuming SDK or App resolves a compatible exact dependency graph.
  consumer_repository: https://github.com/kumwe/extension-sdk
  dependency_or_native_change: Select an independently verified, compatible exact Producer version in the SDK and Core dependency graphs.
  namespace_or_api_replacements: []
  files_to_update:
    - composer.json
    - composer.lock
  files_to_remove: []
  tests_to_remove: []
  tests_to_retain_or_add:
    - Keep host authorization, persistence, Studio lifecycle, rendering integration and recovery tests inventoried here.
    - Run generated-extension and standalone no-dev archive consumers.
  di_or_provisioning_changes:
    - Retain explicit host port construction; no new provider or runtime dependency is introduced.
  capability_index_changes:
    - Record Producer package ownership from its canonical manifests when the consumer dependency changes.
  changelog_and_evidence_changes:
    - Attach the independent release attestation outside the immutable Producer artifact.
  verification_commands:
    - php tools/check.php
    - php tools/verify-clean-consumer.php
    - Affected SDK generated project and Core host integration suites for the selected dependency version
---

# Producer package release record

## Package contract

Producer implements the pinned Studio contract through portable PHP wire handling, document admission,
rendering, stylesheets and deployment helpers. The [host agreement](host-agreement.md) defines the
boundary with Core and other applications. This record preserves source baselines, consumer ownership
and digest evidence; current package versions are published on
[Packagist](https://packagist.org/packages/kumwe/producer) and in the [changelog](../CHANGELOG.md).

## Public API and responsibility

The three canonical manifests describe the 76 exported types. The [public API](public-api.md) is
produced from the same reflection metadata as the complete signature pin in
[resources/public-api.json](../resources/public-api.json). Host ports supply authority and storage.

## Dependencies and semantic inputs

Studio `0.1.0-beta.3` at `42b149251a9f17a2ef8f32db0d9dd1ac2fcfec8a` is the exact semantic source in
[PIN.json](../resources/studio-contract/PIN.json). Checks bind 55 schemas, 301 corpus members, browser
assets, SRI and 14 redistribution notices. Producer claims zero Studio conformance profiles.
A Studio update requires its own reviewed pin change and package release.

## Consumer contract

The [consumer inventory](consumer-inventory.json) records the Core code and tests examined at the
baseline above. Existing names are canonical. Rescan the current consumer before changing its
exact dependency; preserve host authorization, persistence, lifecycle, recovery and delivery tests.
The [host guide](host-guide.md) documents construction, operations and browser deployment.

## Test ownership

Producer owns runtime, boundary, schema and renderer corpus tests. Core owns application adapters,
authorization, persistence, lifecycle and browser integration. The [test ownership policy](test-ownership.md)
records the division; package CI binds all 76 types and generated signatures to actual reflection.

## Consumer verification

Require the complete supported PHP gate and exact archive consumer checks. For independent adoption
evidence, bind the published tag, source, archive, manifests and registry coordinate outside the
immutable artifact. Run affected SDK generated projects and Core host suites against that version.

## Compatibility and drift

The source and consumer baselines above are immutable comparison inputs. They do not identify a
future release commit or assert that a current consumer has been qualified. Preserve the exact
Studio tuple, original API profile and ownership digests; review API and host compatibility before
any dependency or Studio pin change.

## Validation

From a source checkout, run `composer validate --strict`, `composer install`, `composer audit`,
`php tools/check.php` and `php tools/verify-clean-consumer.php`. The reusable CI runs PHP 8.1–8.5,
archive verification and release automation regressions. CI and independent evidence report their
own observed results; see the [release policy](releasing.md).
