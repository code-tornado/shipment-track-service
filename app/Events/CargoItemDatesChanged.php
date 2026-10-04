<?php

namespace App\Events;

use App\Models\CargoItem;
use App\Models\DateChange;
use Illuminate\Foundation\Events\Dispatchable;

class CargoItemDatesChanged
{
    use Dispatchable;

    /**
     * @param  list<DateChange>  $changes
     */
    public function __construct(
        public readonly CargoItem $item,
        public readonly array $changes,
    ) {}
}
