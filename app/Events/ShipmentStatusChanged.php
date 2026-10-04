<?php

namespace App\Events;

use App\Models\Shipment;
use App\Models\StatusHistory;
use Illuminate\Foundation\Events\Dispatchable;

class ShipmentStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly StatusHistory $entry,
    ) {}
}
