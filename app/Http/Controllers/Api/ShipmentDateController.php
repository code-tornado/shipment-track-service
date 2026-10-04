<?php

namespace App\Http\Controllers\Api;

use App\Enums\DateField;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeShipmentDatesRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\Actor;
use App\Services\DateChangeService;
use Illuminate\Support\Facades\DB;

class ShipmentDateController extends Controller
{
    public function update(ChangeShipmentDatesRequest $request, Shipment $shipment, DateChangeService $service): ShipmentResource
    {
        $data = $request->validated();
        $actor = Actor::user($request->user());

        $shipmentDates = array_intersect_key($data, array_flip(['etd', 'eta', 'ata']));

        DB::transaction(function () use ($shipment, $shipmentDates, $data, $actor, $service) {
            if ($shipmentDates) {
                $service->changeShipmentDates($shipment, $shipmentDates, $data['reason'], $actor);
            }

            if (array_key_exists('customer_delivery_date', $data)) {
                foreach ($shipment->cargoItems()->whereNull('delivered_at')->get() as $item) {
                    $service->changeCargoItemDates(
                        $item,
                        [DateField::CustomerDeliveryDate->value => $data['customer_delivery_date']],
                        $data['reason'],
                        $actor,
                    );
                }
            }
        });

        return new ShipmentResource(ShipmentController::loadDetails($shipment->refresh()));
    }
}
