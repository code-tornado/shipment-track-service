<?php

namespace App\Tracking;

use Carbon\CarbonImmutable;

/** What a last-mile carrier (e.g. Shipmondo) knows about one cargo item. */
final class DeliveryUpdate
{
    public function __construct(
        public readonly ?CarbonImmutable $plannedDate = null,
        public readonly ?CarbonImmutable $deliveredAt = null,
        public readonly ?string $note = null,
        public readonly ?CarbonImmutable $occurredAt = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->plannedDate === null && $this->deliveredAt === null;
    }
}
