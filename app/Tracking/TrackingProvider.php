<?php

namespace App\Tracking;

use App\Models\CargoItem;
use App\Models\Container;

/**
 * Source of status and date updates other than manual entry.
 *
 * Two legs can be automated independently: the sea leg (a container-tracking
 * API keyed by container number) and the last mile from the storage facility
 * to the fish farm (Shipmondo, keyed by the parcel/tag). A provider returns
 * null when it has nothing for the given container or item; the sync service
 * then applies whatever came back through the same services (and audit
 * trail) that manual changes use.
 */
interface TrackingProvider
{
    public function name(): string;

    public function trackContainer(Container $container): ?TrackingUpdate;

    public function trackDelivery(CargoItem $item): ?DeliveryUpdate;
}
