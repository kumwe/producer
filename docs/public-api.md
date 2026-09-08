# Producer public API

The 70 exported types below are generated from the existing reflection contract.
resources/public-api.json retains the complete legacy signature and default-value pin.
The canonical version 2 projection is resources/public-api/v1.json.

Construct services explicitly with the host ports described in docs/host-agreement.md.
Producer supplies no authority, storage, ambient service container or runtime Composer dependencies.

## Kumwe\Producer\Canonical\CanonicalEncodingException

Class; source: src/Canonical/CanonicalEncodingException.php.

- __construct(string $rejection, string $message): no declared return type
- rejection(): string

## Kumwe\Producer\Canonical\CanonicalJson

Class; source: src/Canonical/CanonicalJson.php.

- compareCodeUnits(string $left, string $right): int
- decode(string $json): mixed
- digest(mixed $value, int $maximumDepth (optional)): string
- stringify(mixed $value, int $maximumDepth (optional)): string
- Constant DEFAULT_MAXIMUM_DEPTH: untyped.

## Kumwe\Producer\Css\BaseStylesheet

Class; source: src/Css/BaseStylesheet.php.

- css(): string

## Kumwe\Producer\Css\CssException

Class; source: src/Css/CssException.php.


## Kumwe\Producer\Css\ScopedStylesheet

Class; source: src/Css/ScopedStylesheet.php.

- compile(string $scope, object|array $sheet): string
- Constant FORBIDDEN_PATTERN: untyped.
- Constant VALUE_PATTERN: untyped.

## Kumwe\Producer\Css\ThemeStylesheet

Class; source: src/Css/ThemeStylesheet.php.

- compile(array $tokens): string
- document(array $tokens (optional)): string

## Kumwe\Producer\Error\ContractGrammar

Class; source: src/Error/ContractGrammar.php.

- isBoundedText(string $value, int $minimumLength, int $maximumLength): bool
- isLocalName(string $value): bool
- isLocale(string $value): bool
- isQualifiedName(string $value): bool
- isRevision(string $value): bool
- isSafeJsonMemberName(string $value): bool
- isSemanticVersion(string $value): bool
- isStableId(string $value): bool

## Kumwe\Producer\Error\Diagnostic

Class; source: src/Error/Diagnostic.php.

- __construct(string $code, string $severity, Kumwe\Producer\Error\MessageReference $message, ?Kumwe\Producer\Error\DiagnosticLocation $location (optional), array $parameters (optional), array $remediations (optional)): no declared return type
- code(): string
- location(): ?Kumwe\Producer\Error\DiagnosticLocation
- message(): Kumwe\Producer\Error\MessageReference
- parameters(): array
- remediations(): array
- severity(): string
- toDocument(): stdClass
- Constant SEVERITIES: untyped.

## Kumwe\Producer\Error\DiagnosticLocation

Class; source: src/Error/DiagnosticLocation.php.

- __construct(?string $artifactId (optional), ?string $nodeId (optional), ?array $fieldPath (optional), ?string $jsonPointer (optional)): no declared return type
- artifactId(): ?string
- fieldPath(): ?array
- jsonPointer(): ?string
- nodeId(): ?string
- toDocument(): stdClass

## Kumwe\Producer\Error\HostError

Class; source: src/Error/HostError.php.

- cancelled(Kumwe\Producer\Error\MessageReference $message, array $diagnostics (optional), ?string $correlationId (optional)): Kumwe\Producer\Error\HostError
- category(): string
- conflict(Kumwe\Producer\Error\MessageReference $message, ?string $currentRevision (optional), array $diagnostics (optional), ?string $correlationId (optional)): Kumwe\Producer\Error\HostError
- correlationId(): ?string
- diagnostics(): array
- forbidden(Kumwe\Producer\Error\MessageReference $message, array $diagnostics (optional), ?string $correlationId (optional)): Kumwe\Producer\Error\HostError
- fromCanonicalBytes(string $bytes): Kumwe\Producer\Error\HostError
- incompatible(Kumwe\Producer\Error\MessageReference $message, array $diagnostics (optional), ?string $correlationId (optional)): Kumwe\Producer\Error\HostError
- internal(Kumwe\Producer\Error\MessageReference $message, array $diagnostics (optional), ?string $correlationId (optional)): Kumwe\Producer\Error\HostError
- invalidRequest(Kumwe\Producer\Error\MessageReference $message, array $diagnostics (optional), ?string $correlationId (optional)): Kumwe\Producer\Error\HostError
- limitExceeded(Kumwe\Producer\Error\MessageReference $message, array $diagnostics (optional), ?string $correlationId (optional)): Kumwe\Producer\Error\HostError
- message(): Kumwe\Producer\Error\MessageReference
- notFound(Kumwe\Producer\Error\MessageReference $message, array $diagnostics (optional), ?string $correlationId (optional)): Kumwe\Producer\Error\HostError
- rateLimited(Kumwe\Producer\Error\MessageReference $message, ?int $retryAfterMilliseconds (optional), array $diagnostics (optional), ?string $correlationId (optional)): Kumwe\Producer\Error\HostError
- retryAfterMilliseconds(): ?int
- retryable(): bool
- revision(): ?string
- toCanonicalJson(): string
- toDocument(): stdClass
- unauthenticated(Kumwe\Producer\Error\MessageReference $message, array $diagnostics (optional), ?string $correlationId (optional)): Kumwe\Producer\Error\HostError
- unavailable(Kumwe\Producer\Error\MessageReference $message, bool $retryable (optional), ?int $retryAfterMilliseconds (optional), array $diagnostics (optional), ?string $correlationId (optional)): Kumwe\Producer\Error\HostError
- validationFailed(Kumwe\Producer\Error\MessageReference $message, array $diagnostics (optional), ?string $correlationId (optional)): Kumwe\Producer\Error\HostError
- Constant CATEGORIES: untyped.
- Constant CONTRACT_VERSION: untyped.
- Constant MAXIMUM_RETRY_AFTER_MILLISECONDS: untyped.

## Kumwe\Producer\Error\HostRefusal

Class; source: src/Error/HostRefusal.php.

- __construct(Kumwe\Producer\Error\HostError $error, bool $commitsState (optional)): no declared return type
- commitsState(): bool
- error(): Kumwe\Producer\Error\HostError

## Kumwe\Producer\Error\MessageReference

Class; source: src/Error/MessageReference.php.

- __construct(string $key, ?string $defaultMessage (optional)): no declared return type
- defaultMessage(): ?string
- key(): string
- toDocument(): stdClass

## Kumwe\Producer\Render\BindingResolution

Class; source: src/Render/BindingResolution.php.

- available(mixed $value): Kumwe\Producer\Render\BindingResolution
- hidden(): Kumwe\Producer\Render\BindingResolution
- isAvailable(): bool
- isHidden(): bool
- unavailable(): Kumwe\Producer\Render\BindingResolution
- value(): mixed

## Kumwe\Producer\Render\BlockCoordinate

Class; source: src/Render/BlockCoordinate.php.

- __construct(string $type, string $version, string $revision): no declared return type
- key(): string
- versionKey(): string
- Property $revision: string.
- Property $type: string.
- Property $version: string.

## Kumwe\Producer\Render\BlockRenderer

Interface; source: src/Render/BlockRenderer.php.

- render(stdClass $node, string $scope, Kumwe\Producer\Render\RenderState $state): string

## Kumwe\Producer\Render\BlockRendererRegistry

Class; source: src/Render/BlockRendererRegistry.php.

- draftRendererFor(string $type, string $version): ?Kumwe\Producer\Render\BlockRenderer
- register(Kumwe\Producer\Render\BlockCoordinate $coordinate, Kumwe\Producer\Render\BlockRenderer $renderer): void
- rendererFor(Kumwe\Producer\Render\BlockCoordinate $coordinate): ?Kumwe\Producer\Render\BlockRenderer
- supports(Kumwe\Producer\Render\BlockCoordinate $coordinate): bool
- types(): array
- withCoreCatalog(): Kumwe\Producer\Render\BlockRendererRegistry

## Kumwe\Producer\Render\BlockTypes

Class; source: src/Render/BlockTypes.php.

- all(): array
- Constant ACCORDION: untyped.
- Constant ACCORDION_ITEM: untyped.
- Constant ARTICLE: untyped.
- Constant ATTACHMENT: untyped.
- Constant AUDIO: untyped.
- Constant BADGE: untyped.
- Constant CALLOUT: untyped.
- Constant CALL_TO_ACTION: untyped.
- Constant CARD: untyped.
- Constant CHART: untyped.
- Constant CODE: untyped.
- Constant COLUMNS: untyped.
- Constant CONTENT_COLLECTION: untyped.
- Constant CONTENT_REFERENCE: untyped.
- Constant COUNTDOWN: untyped.
- Constant COVER: untyped.
- Constant DESCRIPTION_ITEM: untyped.
- Constant DESCRIPTION_LIST: untyped.
- Constant DIAGRAM: untyped.
- Constant DIALOG: untyped.
- Constant DIVIDER: untyped.
- Constant DRAWING: untyped.
- Constant EMBED: untyped.
- Constant GALLERY: untyped.
- Constant GRID: untyped.
- Constant HEADING: untyped.
- Constant ICON: untyped.
- Constant IMAGE: untyped.
- Constant LABEL: untyped.
- Constant MATH: untyped.
- Constant MONEY: untyped.
- Constant NAVIGATION: untyped.
- Constant NAVIGATION_ITEM: untyped.
- Constant NOTICE: untyped.
- Constant POPOVER: untyped.
- Constant PROGRESS: untyped.
- Constant RICH_TEXT: untyped.
- Constant SEARCH: untyped.
- Constant SECTION: untyped.
- Constant SPINNER: untyped.
- Constant STACK: untyped.
- Constant TAB: untyped.
- Constant TABLE: untyped.
- Constant TABS: untyped.
- Constant VIDEO: untyped.

## Kumwe\Producer\Render\Block\DataBlocks

Class; source: src/Render/Block/DataBlocks.php.

- render(stdClass $node, string $scope, Kumwe\Producer\Render\RenderState $state): string
- types(): array

## Kumwe\Producer\Render\Block\InteractiveBlocks

Class; source: src/Render/Block/InteractiveBlocks.php.

- render(stdClass $node, string $scope, Kumwe\Producer\Render\RenderState $state): string
- types(): array

## Kumwe\Producer\Render\Block\LayoutBlocks

Class; source: src/Render/Block/LayoutBlocks.php.

- render(stdClass $node, string $scope, Kumwe\Producer\Render\RenderState $state): string
- types(): array

## Kumwe\Producer\Render\Block\MediaBlocks

Class; source: src/Render/Block/MediaBlocks.php.

- render(stdClass $node, string $scope, Kumwe\Producer\Render\RenderState $state): string
- types(): array

## Kumwe\Producer\Render\Block\TextBlocks

Class; source: src/Render/Block/TextBlocks.php.

- render(stdClass $node, string $scope, Kumwe\Producer\Render\RenderState $state): string
- types(): array

## Kumwe\Producer\Render\CompositionRenderer

Class; source: src/Render/CompositionRenderer.php.

- __construct(?Kumwe\Producer\Render\BlockRendererRegistry $registry (optional)): no declared return type
- render(array $roots, ?Kumwe\Producer\Render\RenderContext $context (optional)): Kumwe\Producer\Render\RenderResult
- renderDocument(stdClass $document, ?Kumwe\Producer\Render\RenderContext $context (optional)): Kumwe\Producer\Render\RenderResult
- renderNodes(array $nodes, Kumwe\Producer\Render\RenderState $state): string
- scopeFor(string $nodeId): string

## Kumwe\Producer\Render\Enhancement

Class; source: src/Render/Enhancement.php.

- __construct(string $kind, string $nodeId, string $scope, array $details (optional)): no declared return type
- Property $details: array.
- Property $kind: string.
- Property $nodeId: string.
- Property $scope: string.

## Kumwe\Producer\Render\ProductionValues

Class; source: src/Render/ProductionValues.php.

- parseChartSpec(mixed $value): stdClass
- parseDrawingDocument(mixed $value): stdClass
- parseMoneyValue(mixed $value): stdClass
- parsePresentationIntent(mixed $value): stdClass
- parseTableDocument(mixed $value): stdClass

## Kumwe\Producer\Render\Properties

Class; source: src/Render/Properties.php.

- enumProperty(mixed $value, array $allowed, string $fallback): string
- integerProperty(mixed $value, int $minimum, int $maximum, int $fallback): int
- property(stdClass $node, string $name): mixed
- stringProperty(mixed $value, string $fallback): string
- stringValue(mixed $value): string
- stringValueOr(mixed $value, string $fallback): string
- toneProperty(mixed $value): string

## Kumwe\Producer\Render\RenderContext

Class; source: src/Render/RenderContext.php.

- __construct(bool $allowBlobMedia (optional), ?callable $resolveBinding (optional), ?callable $resolveMedia (optional), array $scopedStyles (optional), ?array $previewMarkerMap (optional), Kumwe\Producer\Render\RenderPolicy $policy (optional)): no declared return type
- previewMarkerFor(string $nodeId): ?string
- Property $allowBlobMedia: bool.
- Property $policy: Kumwe\Producer\Render\RenderPolicy.
- Property $previewMarkerMap: ?array.
- Property $resolveBinding: ?Closure.
- Property $resolveMedia: ?Closure.
- Property $scopedStyles: array.

## Kumwe\Producer\Render\RenderException

Class; source: src/Render/RenderException.php.


## Kumwe\Producer\Render\RenderPolicy

Enum; source: src/Render/RenderPolicy.php.

- Enum cases and backed values: {"backing_type":"string","cases":[{"name":"Fallback","value":"fallback"},{"name":"RequireRegistered","value":"require-registered"}]}.

## Kumwe\Producer\Render\RenderResult

Class; source: src/Render/RenderResult.php.

- __construct(string $html, string $css, array $enhancements): no declared return type
- enhancementNames(): array
- Property $css: string.
- Property $enhancements: array.
- Property $html: string.

## Kumwe\Producer\Render\RenderState

Class; source: src/Render/RenderState.php.

- __construct(Kumwe\Producer\Render\RenderContext $context, Kumwe\Producer\Render\CompositionRenderer $renderer, array $blockCoordinates (optional)): no declared return type
- bindingResolution(stdClass $node, string $port): Kumwe\Producer\Render\BindingResolution
- bindingValue(stdClass $node, string $port): mixed
- enhance(string $kind, stdClass $node, string $scope, array $details (optional)): void
- isNodeHidden(string $nodeId): bool
- renderChildren(stdClass $node, string $slot): string
- renderNodes(array $nodes): string
- resolvedMedia(mixed $value): ?Kumwe\Producer\Render\ResolvedMedia
- slotNodes(stdClass $node, string $slot): array
- Property $blockCoordinates: array.
- Property $context: Kumwe\Producer\Render\RenderContext.
- Property $css: array.
- Property $enhancements: array.
- Property $previewMarkers: array.

## Kumwe\Producer\Render\ResolvedMedia

Class; source: src/Render/ResolvedMedia.php.

- __construct(string $src, string $altText (optional), ?string $caption (optional), ?string $mediaType (optional), int|float|null $width (optional), int|float|null $height (optional)): no declared return type
- dimensionsAttribute(): string
- fromDescriptor(stdClass $descriptor): Kumwe\Producer\Render\ResolvedMedia
- withSrc(string $src): Kumwe\Producer\Render\ResolvedMedia
- Property $altText: string.
- Property $caption: ?string.
- Property $height: int|float|null.
- Property $mediaType: ?string.
- Property $src: string.
- Property $width: int|float|null.

## Kumwe\Producer\Render\ResolvedResource

Class; source: src/Render/ResolvedResource.php.

- __construct(string $id, string $label, ?string $summary (optional), ?string $url (optional)): no declared return type
- parse(mixed $value): ?Kumwe\Producer\Render\ResolvedResource
- Property $id: string.
- Property $label: string.
- Property $summary: ?string.
- Property $url: ?string.

## Kumwe\Producer\Render\RichText

Class; source: src/Render/RichText.php.

- parse(mixed $value): stdClass
- project(stdClass $document): array
- render(stdClass $document): string

## Kumwe\Producer\Render\SafeMarkup

Class; source: src/Render/SafeMarkup.php.

- escapeAttribute(string $value): string
- escapeHtml(string $value): string
- isFragment(mixed $value): bool
- number(int|float $value): string
- renderFragment(stdClass $fragment): string
- safeMediaUrl(Kumwe\Producer\Render\ResolvedMedia $media, bool $allowBlob): ?string
- safeUrl(string $value): ?string

## Kumwe\Producer\Schema\CodeUnitOrder

Class; source: src/Schema/CodeUnitOrder.php.

- compare(string $left, string $right): int
- sortedMemberNames(stdClass $value): array

## Kumwe\Producer\Schema\SchemaAdmissionException

Class; source: src/Schema/SchemaAdmissionException.php.

- __construct(string $rejection, string $schemaPath, string $message): no declared return type
- rejection(): string
- schemaPath(): string

## Kumwe\Producer\Schema\SchemaInstanceDiagnostic

Class; source: src/Schema/SchemaInstanceDiagnostic.php.

- __construct(string $instancePath, string $keyword, string $message): no declared return type
- Property $instancePath: string.
- Property $keyword: string.
- Property $message: string.

## Kumwe\Producer\Schema\SchemaPropertyProfile

Class; source: src/Schema/SchemaPropertyProfile.php.

- admit(mixed $schema): Kumwe\Producer\Schema\SchemaPropertyValidator
- Constant LIMITS: untyped.

## Kumwe\Producer\Schema\SchemaPropertyValidator

Class; source: src/Schema/SchemaPropertyValidator.php.

- __construct(stdClass $root, SplObjectStorage $references): no declared return type
- diagnostics(): ?array
- validate(mixed $instance): bool

## Kumwe\Producer\Schema\StudioBrowserArtifacts

Class; source: src/Schema/StudioBrowserArtifacts.php.

- __construct(string $release, string $manifestName, string $manifestSchema, string $authoringArchiveStem, string $authoringAssetRole, string $authoringLoading, string $enhancementAssetRole, string $enhancementLoading, string $enhancementPackage, string $enhancementPackageBasePath): no declared return type
- authoringArchiveStem(): string
- authoringAssetRole(): string
- authoringLoading(): string
- enhancementAssetRole(): string
- enhancementLoading(): string
- enhancementPackage(): string
- enhancementPackageBasePath(): string
- manifestName(): string
- manifestSchema(): string

## Kumwe\Producer\Schema\StudioBrowserAsset

Class; source: src/Schema/StudioBrowserAsset.php.

- __construct(string $role, string $path, string $package, int $bytes, int $budgetBytes, string $contentHash, string $integrity, bool $minified): no declared return type
- budgetBytes(): int
- bytes(): int
- contentHash(): string
- integrity(): string
- minified(): bool
- package(): string
- path(): string
- role(): string

## Kumwe\Producer\Schema\StudioContractRelease

Class; source: src/Schema/StudioContractRelease.php.

- __construct(string $contractVersion, string $release, string $protocolVersion, string $corpusManifestDigest, array $claimedProfiles, array $packages, string $sourceCommit, array $packageIntegrities, Kumwe\Producer\Schema\StudioBrowserArtifacts $browserArtifacts, bool $releaseReady, array $releaseBlockers, string $recordSha256): no declared return type
- browserArtifacts(): Kumwe\Producer\Schema\StudioBrowserArtifacts
- claimedProfiles(): array
- contractVersion(): string
- corpusManifestDigest(): string
- packageIntegrities(): array
- packages(): array
- protocolVersion(): string
- recordSha256(): string
- release(): string
- releaseBlockers(): array
- releaseReady(): bool
- sourceCommit(): string

## Kumwe\Producer\Schema\StudioContractResources

Class; source: src/Schema/StudioContractResources.php.

- browserAsset(string $role): Kumwe\Producer\Schema\StudioBrowserAsset
- browserAssetBytes(string $role): string
- browserManifestBytes(): string
- releaseRecord(): Kumwe\Producer\Schema\StudioContractRelease
- testkitBytes(string $relative): string
- testkitManifestBytes(): string

## Kumwe\Producer\Schema\StudioDocumentSchemaRegistry

Class; source: src/Schema/StudioDocumentSchemaRegistry.php.

- fromVendoredCorpus(): Kumwe\Producer\Schema\StudioDocumentSchemaRegistry
- validate(string $kind, mixed $document): Kumwe\Producer\Schema\StudioDocumentValidation
- Constant CONTRIBUTION_KINDS: untyped.
- Constant DOCUMENT_KINDS: untyped.

## Kumwe\Producer\Schema\StudioDocumentValidation

Class; source: src/Schema/StudioDocumentValidation.php.

- __construct(bool $valid, array $diagnostics): no declared return type
- diagnostics(): array
- valid(): bool

## Kumwe\Producer\Wire\Dispatcher

Class; source: src/Wire/Dispatcher.php.

- __construct(Kumwe\Producer\Wire\Port\HostAdapterInterface $host, Kumwe\Producer\Wire\StrictResponder $responder (optional), int $maximumBodyBytes (optional)): no declared return type
- dispatch(string $route, string $body): Kumwe\Producer\Wire\Response

## Kumwe\Producer\Wire\HostResult

Class; source: src/Wire/HostResult.php.

- __construct(mixed $value, ?string $revision (optional)): no declared return type
- fromCanonicalBytes(string $bytes): Kumwe\Producer\Wire\HostResult
- toDocument(): stdClass
- Property $revision: ?string.
- Property $value: mixed.

## Kumwe\Producer\Wire\JsonShapeViolation

Class; source: src/Wire/JsonShapeViolation.php.

- __construct(string $reason, string $pointer, string $message): no declared return type
- pointer(): string
- reason(): string

## Kumwe\Producer\Wire\JsonValueGuard

Class; source: src/Wire/JsonValueGuard.php.

- assert(mixed $value): void
- Constant MAXIMUM_ITEMS: untyped.
- Constant MAXIMUM_MEMBERS: untyped.

## Kumwe\Producer\Wire\MutationOutcome

Class; source: src/Wire/MutationOutcome.php.

- __construct(?string $intentDigest, Kumwe\Producer\Wire\HostResult|Kumwe\Producer\Error\HostError $outcome): no declared return type
- outcome(): Kumwe\Producer\Wire\HostResult|Kumwe\Producer\Error\HostError
- Property $intentDigest: ?string.

## Kumwe\Producer\Wire\Operation

Class; source: src/Wire/Operation.php.

- __construct(string $capability, string $route, string $method, string $port, string $portCapability, bool $expectsRevision, bool $mutating, bool $required): no declared return type
- toDocument(): stdClass
- Property $capability: string.
- Property $expectsRevision: bool.
- Property $method: string.
- Property $mutating: bool.
- Property $port: string.
- Property $portCapability: string.
- Property $required: bool.
- Property $route: string.

## Kumwe\Producer\Wire\OperationRegistry

Class; source: src/Wire/OperationRegistry.php.

- all(): array
- byCapability(string $capability): Kumwe\Producer\Wire\Operation
- byRoute(string $route): Kumwe\Producer\Wire\Operation
- document(): stdClass
- isCapability(string $capability): bool
- isRoute(string $route): bool
- Constant CONTRACT_VERSION: untyped.

## Kumwe\Producer\Wire\Port\ArtifactPortInterface

Interface; source: src/Wire/Port/ArtifactPortInterface.php.

- dependencies(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- load(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- publish(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- save(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- unpublish(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult

## Kumwe\Producer\Wire\Port\AuthoringPortInterface

Interface; source: src/Wire/Port/AuthoringPortInterface.php.

- listTypes(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- planSave(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- resolveTarget(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- saveAsNewType(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- saveItem(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- saveNewTypeVersion(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- start(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult

## Kumwe\Producer\Wire\Port\AuthorizationInterface

Interface; source: src/Wire/Port/AuthorizationInterface.php.

- authorize(Kumwe\Producer\Wire\Operation $operation, Kumwe\Producer\Wire\RequestEnvelope $request): ?Kumwe\Producer\Error\HostError

## Kumwe\Producer\Wire\Port\HostAdapterInterface

Interface; source: src/Wire/Port/HostAdapterInterface.php.

- artifact(): Kumwe\Producer\Wire\Port\ArtifactPortInterface
- authoring(): ?Kumwe\Producer\Wire\Port\AuthoringPortInterface
- authorization(): Kumwe\Producer\Wire\Port\AuthorizationInterface
- localization(): ?Kumwe\Producer\Wire\Port\LocalizationPortInterface
- media(): ?Kumwe\Producer\Wire\Port\MediaPortInterface
- model(): ?Kumwe\Producer\Wire\Port\ModelPortInterface
- mutations(): Kumwe\Producer\Wire\Port\MutationBoundaryInterface
- permission(): ?Kumwe\Producer\Wire\Port\PermissionPortInterface
- preview(): ?Kumwe\Producer\Wire\Port\PreviewPortInterface
- recovery(): ?Kumwe\Producer\Wire\Port\RecoveryPortInterface
- resource(): ?Kumwe\Producer\Wire\Port\ResourcePortInterface
- telemetry(): ?Kumwe\Producer\Wire\Port\TelemetryPortInterface

## Kumwe\Producer\Wire\Port\LocalizationPortInterface

Interface; source: src/Wire/Port/LocalizationPortInterface.php.

- messages(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult

## Kumwe\Producer\Wire\Port\MediaPortInterface

Interface; source: src/Wire/Port/MediaPortInterface.php.

- abortUpload(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- authorizeUpload(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- completeUpload(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- get(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- importExternal(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- list(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- uploadStatus(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult

## Kumwe\Producer\Wire\Port\ModelPortInterface

Interface; source: src/Wire/Port/ModelPortInterface.php.

- get(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- list(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult

## Kumwe\Producer\Wire\Port\MutationBoundaryInterface

Interface; source: src/Wire/Port/MutationBoundaryInterface.php.

- execute(Kumwe\Producer\Wire\Operation $operation, Kumwe\Producer\Wire\RequestEnvelope $request, ?string $scopeKey, ?string $intentDigest, callable $mutation): Kumwe\Producer\Wire\MutationOutcome

## Kumwe\Producer\Wire\Port\PermissionPortInterface

Interface; source: src/Wire/Port/PermissionPortInterface.php.

- explain(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- refresh(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult

## Kumwe\Producer\Wire\Port\PreviewPortInterface

Interface; source: src/Wire/Port/PreviewPortInterface.php.

- cancel(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- render(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult

## Kumwe\Producer\Wire\Port\RecoveryPortInterface

Interface; source: src/Wire/Port/RecoveryPortInterface.php.

- discard(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- load(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult
- store(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult

## Kumwe\Producer\Wire\Port\ResourcePortInterface

Interface; source: src/Wire/Port/ResourcePortInterface.php.

- search(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult

## Kumwe\Producer\Wire\Port\TelemetryPortInterface

Interface; source: src/Wire/Port/TelemetryPortInterface.php.

- emit(mixed $arguments, Kumwe\Producer\Wire\RequestContext $context): Kumwe\Producer\Wire\HostResult

## Kumwe\Producer\Wire\RequestContext

Class; source: src/Wire/RequestContext.php.

- __construct(string $operationId, string $protocolVersion, string $requestId, string $resourceContextKey, string $sessionGeneration, ?string $expectedRevision (optional), ?string $idempotencyKey (optional), ?string $locale (optional), array $traceContext (optional)): no declared return type
- Property $expectedRevision: ?string.
- Property $idempotencyKey: ?string.
- Property $locale: ?string.
- Property $operationId: string.
- Property $protocolVersion: string.
- Property $requestId: string.
- Property $resourceContextKey: string.
- Property $sessionGeneration: string.
- Property $traceContext: array.

## Kumwe\Producer\Wire\RequestEnvelope

Class; source: src/Wire/RequestEnvelope.php.

- arguments(): mixed
- context(): Kumwe\Producer\Wire\RequestContext
- hasArguments(): bool
- parse(string $body, int $maximumBodyBytes (optional)): Kumwe\Producer\Wire\RequestEnvelope
- Constant DEFAULT_MAXIMUM_BODY_BYTES: untyped.
- Constant WIRE_PROTOCOL_VERSION: untyped.

## Kumwe\Producer\Wire\Response

Class; source: src/Wire/Response.php.

- __construct(string $body, array $headers, ?string $refusalCategory (optional)): no declared return type
- Property $body: string.
- Property $headers: array.
- Property $refusal: bool.
- Property $refusalCategory: ?string.

## Kumwe\Producer\Wire\StrictResponder

Class; source: src/Wire/StrictResponder.php.

- refusal(Kumwe\Producer\Error\HostError $error): Kumwe\Producer\Wire\Response
- result(Kumwe\Producer\Wire\HostResult $result): Kumwe\Producer\Wire\Response
- Constant CONTENT_TYPE: untyped.

