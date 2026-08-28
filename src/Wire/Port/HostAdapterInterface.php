<?php

/**
 * Everything a host hands Producer at the wire boundary.
 *
 * Authorization and the idempotency ledger are always present — Producer
 * fails closed without a decision and never applies a keyed mutation it
 * cannot prove unreplayed. The artifact port is the registry's one
 * required port; every other port is optional, and a request addressed to
 * an absent optional port is refused as unavailable rather than guessed
 * at.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Wire\Port;

interface HostAdapterInterface
{
    public function authorization(): AuthorizationInterface;

    public function idempotency(): IdempotencyLedgerInterface;

    public function artifact(): ArtifactPortInterface;

    public function authoring(): ?AuthoringPortInterface;

    public function localization(): ?LocalizationPortInterface;

    public function media(): ?MediaPortInterface;

    public function model(): ?ModelPortInterface;

    public function permission(): ?PermissionPortInterface;

    public function preview(): ?PreviewPortInterface;

    public function recovery(): ?RecoveryPortInterface;

    public function resource(): ?ResourcePortInterface;

    public function telemetry(): ?TelemetryPortInterface;
}
