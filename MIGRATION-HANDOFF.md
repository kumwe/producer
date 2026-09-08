---
schema: kumwe-migration-handoff/v2
artifact_kind: framework_php
migration_id: KUMWE-MIG-2026-032
change_set: KUMWE-CS-2026-032
state: draft_pr_open
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
  - Existing Producer0.2.1 at e8b2def866b95981b8e7ac521c16420a0f7955c8 owns the unchanged70-type API and Studio
    contract.
  - Studio0.1.0-beta.3 source42b149251a9f17a2ef8f32db0d9dd1ac2fcfec8a remains pinned in resources/studio-contract/PIN.json.
  active_related_pull_requests:
  - https://github.com/kumwe/producer/pull/11
  - https://github.com/kumwe/extension-sdk/pull/15
target:
  repository: https://github.com/kumwe/producer
  artifact_identity: kumwe/producer
  canonical_namespace_or_abi: Kumwe\Producer\
  branch: codex/v2-release-handoff
  pull_request: https://github.com/kumwe/producer/pull/11
ownership:
  responsibility: Portable PHP implementation of the pinned Studio wire, schema, rendering and design-token contract.
  non_responsibilities:
  - Host authentication, authorization, persistence, revisions, lifecycle and delivery.
  - JavaScript compilation, dynamic script generation and runtime Composer dependency discovery.
  - Optional deployment emitters and Twig bridge remain separate roadmap work.
  allowed_dependency_ceiling:
  - PHP
  - ext-json
  - ext-mbstring
  implementation_owner: kumwe/producer
  next_consumer: kumwe/extension-sdk
  public_manifests:
  - path: resources/public-api/v1.json
    sha256: f6c031833a1ecf066486c939052c8452398e001939cb9e41269ba93c2148e269
  - path: resources/capabilities/v1.json
    sha256: d62056117c2c8b02a28b79753013d2a6dc1e6c13f807c9350ca42acc1cbd8071
  - path: resources/service-map/v1.json
    sha256: 32e3206648abbb1d3e94e6ac37705cc9e0069c3eec9e1aa9404a7133924626e1
  - path: resources/public-api.json
    sha256: ccccb11b9c027928b66a7ec0633ef701bc0e2ca58585892035b60e51c705e61e
  - path: resources/studio-contract/PIN.json
    sha256: 3c1b094e59c5bbdeb867b2f12df0b8a88a4855ae61003913444dda8222b4ee77
  - path: resources/studio-contract/protocol/schemas/manifest.json
    sha256: b7d41d6cbe71a770f433ddce12bdcd5582a2be0249191f4845ad5278079ff6e9
  - path: resources/studio-contract/testkit/corpus-manifest.json
    sha256: 3eea5d655bfe86d234c1dedb9c5b7055d173858541ec3c535e1faf8a204294d3
  intentionally_excluded:
  - No App implementation is moved in this existing-package governance successor.
  - All Studio assets, original API profiles and existing runtime source retain their current ownership and bytes.
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
    provider_absence_reason: The host explicitly constructs operations with host ports. Producer has no ambient
      container and its charter forbids runtime Composer dependencies.
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
  changelog_record: CHANGELOG.md / 0.2.2
release_expectations:
  version_policy: Backward-compatible0.2.2 governance successor; preserve original API/Studio pins and every published
    release.
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
next_task:
  phase_name: Verify released Producer0.2.2 and consume it in the compatible extraction package graph
  permitted_only_when:
  - Actual release and exact immutable source/archive/registry identities are independently verified.
  - Final complete Package gate passes on the merged release source.
  - The consuming SDK or App resolves a compatible exact dependency graph.
  consumer_repository: https://github.com/kumwe/extension-sdk
  dependency_or_native_change: Select the independently verified Producer0.2.2 coordinate; App integration is a
    later separate task.
  namespace_or_api_replacements: []
  files_to_update:
  - composer.json
  - composer.lock
  files_to_remove: []
  tests_to_remove: []
  tests_to_retain_or_add:
  - Keep host authorization, persistence, Studio lifecycle, rendering integration and recovery tests inventoried
    here.
  - Run generated-extension and standalone no-dev archive consumers.
  di_or_provisioning_changes:
  - Retain explicit host port construction; no new provider or runtime dependency is introduced.
  capability_index_changes:
  - Record Producer package ownership from canonical manifests during later App adoption.
  changelog_and_evidence_changes:
  - Attach the independent release attestation outside the immutable Producer artifact.
  verification_commands:
  - php tools/check.php
  - php tools/verify-clean-consumer.php
  - Affected SDK generated project and existing App host integration suites at their respective later adoption stages
concurrency:
  likely_conflict_files:
  - composer.json
  - composer.lock
  - MIGRATION-HANDOFF.md
  - resources/public-api/v1.json
  related_migrations: []
  ownership_conflicts: []
  integration_train: null
  resolution_rule: semantic-preservation
governance:
  roadmap_source_sha256: a202155ef1a65f5ab293d4f8397ebf4ac430db7f1e877c776bbe7851e6fe18d8
  roadmap_refs: []
  non_roadmap_refs: []
  completion_claim: false
decisions:
- Keep all70public runtime types and original detailed API manifest byte-identical.
- Add canonical discovery metadata and complete source-derived public documentation without new runtime behavior.
- Ship governance documents and the handoff in source and Composer archives.
- Studio remains the semantic owner; no new Studio contract import or browser artifact is performed.
- Core/App integration and future deployment-emitter/Twig features are excluded from this readiness release.
blockers:
- Final reviewed-head CI, actual publication and external independent release verification remain pending.
---

# Producer version 2 package handoff

## Migration/implementation summary

Producer already owns its PHP implementation and is already a canonical App dependency. This successor adds the version 2 release contract and archive evidence; it moves no App class and changes no runtime algorithm. All70public types, the original detailed API pin and the existing Studio source/resource tuple stay intact.

## Public API and responsibility

The canonical manifests describe portable wire handling, schema admission, typed diagnostics, deterministic rendering and CSS. docs/public-api.md is generated from the same complete reflection metadata as the existing API pin. The original resources/public-api.json preserves signature defaults, constant values and enum detail. Explicit host ports retain authority and storage.

## Capability reuse/semantic input review

Studio0.1.0-beta.3 at42b149251a9f17a2ef8f32db0d9dd1ac2fcfec8a remains the exact imported semantic source recorded in resources/studio-contract/PIN.json. Existing checks bind55protocol schemas,301corpus files, browser/SRI and14redistribution notices. This change imports no new corpus or implementation and preserves the empty claimed-profile list.

## Consumer inventory

docs/consumer-inventory.json records every observed direct Producer reference in the App source and tests at24ecf956423c18933e824b43cea1bfb9127a79a9. Existing names are already canonical, so there are no namespace replacements or deletion instructions. A later App dependency update must first repeat the drift scan and preserve its host-owned integration coverage.

## Test ownership

Producer keeps its runtime, boundary, schema and renderer corpus tests. App authorization, persistence, recovery, lifecycle and host adapter tests remain in the App and are explicitly inventoried. The new metadata gate verifies all70exported types and all generated signatures against actual reflection; archive verification requires the complete governed package boundary.

## Next-task execution notes

Review PR11 and require the complete PHP8.1–8.5 gate before release through the existing default-branch workflow. Independently verify the resulting source/archive/registry identity and attach external evidence. Only then select0.2.2 in the compatible SDK dependency graph. App integration remains a separate next stage.

## Drift check

Producer source input is current main88c43ebb93b39e51407e415d97108cf1c6e1f677, whose delivered runtime is the0.2.1sourcee8b2def866b95981b8e7ac521c16420a0f7955c8. The original70-type API pin and Studio pin/corpus bytes remain unchanged. The referenced App scan is historical evidence; re-scan before later App edits.

## Validation recipe and observed local results

Required validation is the complete php tools/check.php lane, Composer security audit, all five supported PHP CI lanes, exact archive verification and fresh no-dev authoritative consumer. This handoff records the required recipe; it never invents its own final commit/archive digest or an external release attestation. Final results belong to the exact-head CI and independent evidence.
