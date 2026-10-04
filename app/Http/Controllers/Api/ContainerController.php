<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContainerRequest;
use App\Http\Requests\UpdateContainerRequest;
use App\Http\Resources\ContainerResource;
use App\Models\Container;
use App\Models\Shipment;
use App\Rules\ContainerNumber;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;

class ContainerController extends Controller
{
    public function store(StoreContainerRequest $request, Shipment $shipment, ShipmentService $service): JsonResponse
    {
        $container = $service->addContainer($shipment, $request->validated());

        return (new ContainerResource($container->load('cargoItems.customer', 'cargoItems.product', 'shipment')))
            ->response()->setStatusCode(201);
    }

    public function update(UpdateContainerRequest $request, Container $container): ContainerResource
    {
        $data = $request->validated();
        if (isset($data['container_no'])) {
            $data['container_no'] = ContainerNumber::normalize($data['container_no'], $container->shipment->shipping_method);
        }
        $container->update($data);

        return new ContainerResource($container->load('cargoItems.customer', 'cargoItems.product', 'shipment'));
    }

    public function destroy(Container $container): JsonResponse
    {
        $container->delete();

        return response()->json(null, 204);
    }
}
