<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeCargoItemDatesRequest;
use App\Http\Resources\CargoItemResource;
use App\Models\CargoItem;
use App\Services\Actor;
use App\Services\DateChangeService;

class CargoItemDateController extends Controller
{
    public function update(ChangeCargoItemDatesRequest $request, CargoItem $cargoItem, DateChangeService $service): CargoItemResource
    {
        $data = $request->validated();

        $service->changeCargoItemDates(
            $cargoItem,
            array_intersect_key($data, array_flip(['customer_delivery_date', 'delivered_at'])),
            $data['reason'],
            Actor::user($request->user()),
        );

        return new CargoItemResource($cargoItem->refresh()->load('customer', 'product', 'container.shipment', 'dateChanges'));
    }
}
