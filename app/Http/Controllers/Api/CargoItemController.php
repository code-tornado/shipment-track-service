<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCargoItemRequest;
use App\Http\Requests\UpdateCargoItemRequest;
use App\Http\Resources\CargoItemResource;
use App\Models\CargoItem;
use App\Models\Container;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;

class CargoItemController extends Controller
{
    public function store(StoreCargoItemRequest $request, Container $container, ShipmentService $service): JsonResponse
    {
        $item = $service->addCargoItem($container, $request->validated());

        return (new CargoItemResource($item->load('customer', 'product', 'container.shipment')))
            ->response()->setStatusCode(201);
    }

    public function update(UpdateCargoItemRequest $request, CargoItem $cargoItem, ShipmentService $service): CargoItemResource
    {
        $cargoItem->update($service->cargoItemAttributes($request->validated()));

        return new CargoItemResource($cargoItem->load('customer', 'product', 'container.shipment', 'dateChanges'));
    }

    public function destroy(CargoItem $cargoItem): JsonResponse
    {
        $cargoItem->delete();

        return response()->json(null, 204);
    }
}
