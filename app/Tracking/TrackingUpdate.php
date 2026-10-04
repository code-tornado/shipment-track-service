<?php

namespace App\Tracking;

use App\Enums\ShipmentStatus;
use Carbon\CarbonImmutable;

/** What a provider knows about a container's sea leg. */
final class TrackingUpdate
{
    public function __construct(
        public readonly ?ShipmentStatus $status = null,
        public readonly ?CarbonImmutable $eta = null,
        public readonly ?CarbonImmutable $ata = null,
        public readonly ?string $note = null,
        public readonly ?CarbonImmutable $occurredAt = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->status === null && $this->eta === null && $this->ata === null;
    }
}
