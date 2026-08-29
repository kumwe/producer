<?php

declare(strict_types=1);

namespace Kumwe\Producer\Schema;

/**
 * Immutable verdict from validating one decoded document against a pinned
 * Studio document schema.
 *
 * A successful verdict carries no diagnostics. A refusal carries the
 * ordered, de-duplicated diagnostics emitted by the exact Studio schema
 * interpreter. The value is deliberately not an exception: invalid external
 * documents are an expected admission result, while registry corruption and
 * an unsupported document kind remain exceptional at the registry boundary.
 *
 * @since   0.2.0
 */
final class StudioDocumentValidation
{
    /**
     * Hold one internally consistent validation verdict.
     *
     * @param bool                           $valid       Whether the document satisfies its schema.
     * @param list<SchemaInstanceDiagnostic> $diagnostics Ordered distinct failures; empty exactly
     *                                                    when valid.
     *
     * @throws \InvalidArgumentException When the verdict and diagnostics disagree or a member is
     *                                    not a schema diagnostic.
     *
     * @since   0.2.0
     */
    public function __construct(
        private readonly bool $valid,
        private readonly array $diagnostics
    ) {
        if ($valid === ($diagnostics !== [])) {
            throw new \InvalidArgumentException('A Studio document verdict must agree with its diagnostics.');
        }
        foreach ($diagnostics as $diagnostic) {
            if (!$diagnostic instanceof SchemaInstanceDiagnostic) {
                throw new \InvalidArgumentException('Studio document diagnostics must use the canonical type.');
            }
        }
        if (!array_is_list($diagnostics)) {
            throw new \InvalidArgumentException('Studio document diagnostics must be an ordered list.');
        }
    }

    /**
     * Whether the decoded document satisfies the pinned schema exactly.
     *
     * @since   0.2.0
     */
    public function valid(): bool
    {
        return $this->valid;
    }

    /**
     * Ordered distinct instance failures, or an empty list after success.
     *
     * @return list<SchemaInstanceDiagnostic>
     *
     * @since   0.2.0
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }
}
