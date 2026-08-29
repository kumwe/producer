<?php

declare(strict_types=1);

namespace Kumwe\Producer\Render;

use Kumwe\Producer\Error\ContractGrammar;

/**
 * Exact executable coordinate of one dependency-locked block renderer.
 *
 * The trusted host binds a renderer to this value after checking owner,
 * signature, definition, and integrity. Producer performs no owner lookup
 * and never lets renderer code widen the coordinate it was granted.
 *
 * @since   0.2.0
 */
final class BlockCoordinate
{
    /**
     * Prove one Blueprint block dependency coordinate.
     *
     * @param   string  $type      Contract qualified block type.
     * @param   string  $version   Semantic block version.
     * @param   string  $revision  Opaque non-empty revision.
     *
     * @throws  \InvalidArgumentException  When a coordinate is malformed.
     *
     * @since   0.2.0
     */
    public function __construct(
        public readonly string $type,
        public readonly string $version,
        public readonly string $revision,
    ) {
        if (!ContractGrammar::isQualifiedName($type)) {
            throw new \InvalidArgumentException('A block type must be a contract qualified name.');
        }
        if (!ContractGrammar::isSemanticVersion($version)) {
            throw new \InvalidArgumentException('A block version must be Semantic Versioning 2.0.0.');
        }
        if (!ContractGrammar::isRevision($revision)) {
            throw new \InvalidArgumentException('A block revision must be UTF-8 text of 1 to 200 code points.');
        }
    }

    /**
     * Collision-free private registry key for this exact coordinate.
     *
     * @return  string  Type, version, and revision separated by NUL.
     *
     * @since   0.2.0
     */
    public function key(): string
    {
        return $this->type . "\0" . $this->version . "\0" . $this->revision;
    }

    /**
     * Registry key shared by all revisions of this type and version.
     *
     * @return  string  Type and version separated by NUL.
     *
     * @since   0.2.0
     */
    public function versionKey(): string
    {
        return $this->type . "\0" . $this->version;
    }
}
