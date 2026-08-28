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

/**
 * Upload sessions and asset resolution through the host's media service,
 * optional.
 *
 * What the host receives: only calls the dispatcher has already
 * validated, cross-checked against the registry row, and had allowed by
 * the host's own {@see AuthorizationInterface}; arguments are
 * jsonValue-proven and passed through unjudged. What the host must
 * guarantee back: every method answers with a {@see HostResult} carrying
 * the operation's schema-shaped document and refuses only by throwing
 * {@see HostRefusal} with a taxonomy category and a non-disclosing
 * message. Media safety is entirely the host's: policy rejection happens
 * at authorize-upload before any byte moves, received bytes are verified
 * at complete-upload with no trust in a declared media type or checksum,
 * and import-external runs under the host's runtime hardening. The five
 * mutations may carry an idempotency key; the dispatcher then replays
 * their recorded outcomes without re-invoking the port.
 *
 * @since   0.1.0
 */
interface MediaPortInterface
{
    /**
     * studio.operation/media.abort-upload — release an unused grant;
     * never deletes an accepted asset.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The abort acknowledgement.
     *
     * @throws  HostRefusal  A taxonomy refusal — e.g. not-found for an
     *                       unknown grant.
     *
     * @since   0.1.0
     */
    public function abortUpload(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/media.authorize-upload — authorize one declared
     * upload against host policy and return the grant.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The upload grant document.
     *
     * @throws  HostRefusal  A taxonomy refusal — e.g. validation-failed or
     *                       limit-exceeded when the declared upload breaks
     *                       host policy, before any byte moves.
     *
     * @since   0.1.0
     */
    public function authorizeUpload(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/media.complete-upload — close a transferred
     * upload; the host mints the stable asset identity.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The accepted asset document under its minted
     *                      identity.
     *
     * @throws  HostRefusal  A taxonomy refusal — e.g. validation-failed
     *                       when the received bytes fail the host's own
     *                       verification.
     *
     * @since   0.1.0
     */
    public function completeUpload(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/media.get — one asset, or null when unknown.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The asset document, or an explicit null value
     *                      when the asset is unknown.
     *
     * @throws  HostRefusal  A taxonomy refusal.
     *
     * @since   0.1.0
     */
    public function get(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/media.import-external — fetch an external
     * candidate under host runtime hardening.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The imported asset document.
     *
     * @throws  HostRefusal  A taxonomy refusal — e.g. validation-failed
     *                       for a candidate the hardening refuses, or
     *                       unavailable when the fetch cannot run.
     *
     * @since   0.1.0
     */
    public function importExternal(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/media.list — a media page for a query.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The media list page.
     *
     * @throws  HostRefusal  A taxonomy refusal.
     *
     * @since   0.1.0
     */
    public function list(mixed $arguments, RequestContext $context): HostResult;

    /**
     * studio.operation/media.upload-status — poll an accepted asset whose
     * processing has not settled.
     *
     * @param   mixed           $arguments  The decoded argument, already
     *                                      jsonValue-proven; null when the
     *                                      request carried none.
     * @param   RequestContext  $context    The validated envelope context.
     *
     * @return  HostResult  The processing status document.
     *
     * @throws  HostRefusal  A taxonomy refusal — e.g. not-found for an
     *                       unknown asset.
     *
     * @since   0.1.0
     */
    public function uploadStatus(mixed $arguments, RequestContext $context): HostResult;
}
