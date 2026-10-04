<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeStatusRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\Actor;
use App\Services\ShipmentStatusService;
use Carbon\CarbonImmutable;

class ShipmentStatusController extends Controller
{
    public function store(ChangeStatusRequest $request, Shipment $shipment, ShipmentStatusService $service): ShipmentResource
    {
        $data = $request->validated();

        $service->transition(
            $shipment,
            ShipmentStatus::from($data['status']),
            Actor::user($request->user()),
            $data['note'] ?? null,
            isset($data['occurred_at']) ? CarbonImmutable::parse($data['occurred_at']) : null,
        );

        return new ShipmentResource(ShipmentController::loadDetails($shipment->refresh()));
    }
}
