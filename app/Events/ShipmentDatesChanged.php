<?php

namespace App\Events;

use App\Models\DateChange;
use App\Models\Shipment;
use Illuminate\Foundation\Events\Dispatchable;

class ShipmentDatesChanged
{
    use Dispatchable;

    /**
     * @param  list<DateChange>  $changes
     */
    public function __construct(
        public readonly Shipment $shipment,
        public readonly array $changes,
    ) {}
}
