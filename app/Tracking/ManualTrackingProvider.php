<?php

namespace App\Tracking;

use App\Models\CargoItem;
use App\Models\Container;

/** Default: nothing is automated, every change is entered by a user. */
class ManualTrackingProvider implements TrackingProvider
{
    public function name(): string
    {
        return 'manual';
    }

    public function trackContainer(Container $container): ?TrackingUpdate
    {
        return null;
    }

    public function trackDelivery(CargoItem $item): ?DeliveryUpdate
    {
        return null;
    }
}
