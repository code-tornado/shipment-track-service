<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Http\Resources\ShipmentSummaryResource;
use App\Models\Shipment;
use App\Queries\ShipmentQuery;
use App\Services\Actor;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShipmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ShipmentQuery::apply(
            Shipment::query()->with(['containers.cargoItems.customer']),
            $request->query()
        );

        $perPage = min(max((int) $request->query('per_page', 25), 1), 200);

        return ShipmentSummaryResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreShipmentRequest $request, ShipmentService $service): JsonResponse
    {
        $shipment = $service->create($request->validated(), Actor::user($request->user()));

        return (new ShipmentResource($this->loadDetails($shipment)))->response()->setStatusCode(201);
    }

    public function show(Shipment $shipment): ShipmentResource
    {
        return new ShipmentResource($this->loadDetails($shipment));
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): ShipmentResource
    {
        $shipment->update($request->validated());

        return new ShipmentResource($this->loadDetails($shipment));
    }

    public function destroy(Shipment $shipment): JsonResponse
    {
        $shipment->delete();

        return response()->json(null, 204);
    }

    public static function loadDetails(Shipment $shipment): Shipment
    {
        return $shipment->load([
            'containers.cargoItems.customer',
            'containers.cargoItems.product',
            'statusHistory',
            'dateChanges',
        ]);
    }
}
