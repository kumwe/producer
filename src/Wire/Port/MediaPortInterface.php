<?php

/**
 * The media port, studio.port/media.
 *
 * Upload sessions and asset resolution through the host's media service.
 * Policy rejection happens at authorize-upload, before any byte moves;
 * the host verifies received bytes at complete-upload and never trusts a
 * declared media type or checksum; import-external runs under the host's
 * runtime hardening. Refusal is a thrown {@see HostRefusal}.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

use Kumwe\Producer\Error\HostRefusal;
use Kumwe\Producer\Wire\HostResult;
use Kumwe\Producer\Wire\RequestContext;

interface MediaPortInterface
{
    /**
     * studio.operation/media.abort-upload — release an unused grant;
     * never deletes an accepted asset.
     *
     * @throws HostRefusal
     */
    public function abortUpload(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/media.authorize-upload — authorize one declared
     * upload against host policy and return the grant.
     *
     * @throws HostRefusal
     */
    public function authorizeUpload(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/media.complete-upload — close a transferred
     * upload; the host mints the stable asset identity.
     *
     * @throws HostRefusal
     */
    public function completeUpload(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/media.get — one asset, or null when unknown.
     *
     * @throws HostRefusal
     */
    public function get(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/media.import-external — fetch an external
     * candidate under host runtime hardening.
     *
     * @throws HostRefusal
     */
    public function importExternal(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/media.list — a media page for a query.
     *
     * @throws HostRefusal
     */
    public function list(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/media.upload-status — poll an accepted asset whose
     * processing has not settled.
     *
     * @throws HostRefusal
     */
    public function uploadStatus(mixed $arguments, RequestContext $context): HostResult;
}
