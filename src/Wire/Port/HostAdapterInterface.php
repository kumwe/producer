<?php

/**
 * Everything a host hands Producer at the wire boundary.
 *
 * Authorization and the host-atomic mutation boundary are always present —
 * Producer fails closed without a decision and never applies a mutation
 * outside the host transaction. The artifact port is the registry's one
 * required port; every other port is optional, and a request addressed to
 * an absent optional port is refused as unavailable rather than guessed
 * at.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

/**
 * The host's side of the wire, complete: the authorities the dispatcher
 * always consults and the nine operation ports of the pinned registry.
 *
 * What the host must guarantee: {@see authorization()},
 * {@see mutations()}, and {@see artifact()} always return an
 * implementation — the dispatcher fails closed without an authorization
 * decision, never applies a mutation outside the host's atomic boundary,
 * and the artifact port is the registry's one required port. Each other
 * accessor either returns its port or null; null is a stable statement
 * that this host does not serve the port, which the dispatcher turns into
 * an unavailable refusal (retryable false), never a guess. Accessors must
 * be side-effect free — they are called on every dispatch.
 *
 * @since   0.1.0
 */
interface HostAdapterInterface
{
    /**
     * The host's authorization authority, consulted first for every
     * operation without exception.
     *
     * @return  AuthorizationInterface  Always an implementation — no
     *                                  decision means the request is
     *                                  refused.
     *
     * @since   0.1.0
     */
    public function authorization(): AuthorizationInterface;

    /**
     * The host's atomic transaction, audit, and optional replay boundary.
     *
     * @return  MutationBoundaryInterface  Always an implementation — it
     *                                     commits mutation, audit, and any
     *                                     protected replay representation in
     *                                     one host transaction.
     *
     * @since   0.1.0
     */
    public function mutations(): MutationBoundaryInterface;

    /**
     * The artifact port, studio.port/artifact — required of every
     * conforming host.
     *
     * @return  ArtifactPortInterface  Always an implementation.
     *
     * @since   0.1.0
     */
    public function artifact(): ArtifactPortInterface;

    /**
     * The optional localization port, studio.port/localization.
     *
     * @return  LocalizationPortInterface|null  The port, or null when this
     *                                          host does not serve it.
     *
     * @since   0.1.0
     */
    public function localization(): ?LocalizationPortInterface;

    /**
     * The optional media port, studio.port/media.
     *
     * @return  MediaPortInterface|null  The port, or null when this host
     *                                   does not serve it.
     *
     * @since   0.1.0
     */
    public function media(): ?MediaPortInterface;

    /**
     * The optional model port, studio.port/model.
     *
     * @return  ModelPortInterface|null  The port, or null when this host
     *                                   does not serve it.
     *
     * @since   0.1.0
     */
    public function model(): ?ModelPortInterface;

    /**
     * The optional permission port, studio.port/permission.
     *
     * @return  PermissionPortInterface|null  The port, or null when this
     *                                        host does not serve it.
     *
     * @since   0.1.0
     */
    public function permission(): ?PermissionPortInterface;

    /**
     * The optional preview port, studio.port/preview.
     *
     * @return  PreviewPortInterface|null  The port, or null when this host
     *                                     does not serve it.
     *
     * @since   0.1.0
     */
    public function preview(): ?PreviewPortInterface;

    /**
     * The optional recovery port, studio.port/recovery.
     *
     * @return  RecoveryPortInterface|null  The port, or null when this
     *                                      host does not serve it.
     *
     * @since   0.1.0
     */
    public function recovery(): ?RecoveryPortInterface;

    /**
     * The optional resource port, studio.port/resource.
     *
     * @return  ResourcePortInterface|null  The port, or null when this
     *                                      host does not serve it.
     *
     * @since   0.1.0
     */
    public function resource(): ?ResourcePortInterface;

    /**
     * The optional telemetry port, studio.port/telemetry.
     *
     * @return  TelemetryPortInterface|null  The port, or null when this
     *                                       host does not serve it.
     *
     * @since   0.1.0
     */
    public function telemetry(): ?TelemetryPortInterface;
}
