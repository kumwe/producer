<?php

declare(strict_types=1);

namespace Kumwe\Producer\Deployment;

/**
 * Refusal raised while locating, emitting or admitting a Studio browser deployment.
 * Carries a stable rejection code so a host maps the refusal without parsing
 * prose; the message is a human diagnostic and never discloses configuration
 * bytes, credentials or filesystem detail.
 * @since   0.3.0
 */
final class DeploymentException extends \RuntimeException
{
    /**
     * Hold the refusal's stable code alongside its human-readable message.
     * @param string $rejection Stable code, e.g. `base-url`, `release-mismatch`,
     *                          `mount-mismatch`, `schema-invalid`, `resource-context`,
     *                          `routing-mismatch`, `oversized`, `identifier`,
     *                          `nonce`, `origin` or `unknown-asset`.
     * @param string $message   Human diagnostic; never used for matching.
     * @since   0.3.0
     */
    public function __construct(private readonly string $rejection, string $message)
    {
        parent::__construct($message);
    }

    /**
     * The stable rejection code the constructor documents.
     * @since   0.3.0
     */
    public function rejection(): string
    {
        return $this->rejection;
    }
}
