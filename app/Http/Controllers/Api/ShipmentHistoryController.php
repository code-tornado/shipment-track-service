<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DateChangeResource;
use App\Http\Resources\StatusHistoryResource;
use App\Models\DateChange;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;

/**
 * One timeline for a shipment: status changes, its own date changes and the
 * date changes of every cargo item it carries, newest first.
 */
class ShipmentHistoryController extends Controller
{
    public function index(Shipment $shipment): JsonResponse
    {
        $itemIds = $shipment->cargoItems()->pluck('cargo_items.id');

        $statusEntries = $shipment->statusHistory()->get()
            ->map(fn ($entry) => (new StatusHistoryResource($entry))->resolve());

        $dateEntries = DateChange::query()
            ->with('subject')
            ->where(function ($q) use ($shipment, $itemIds) {
                $q->where(fn ($q) => $q->where('subject_type', 'shipment')->where('subject_id', $shipment->id))
                    ->orWhere(fn ($q) => $q->where('subject_type', 'cargo_item')->whereIn('subject_id', $itemIds));
            })
            ->get()
            ->map(fn ($entry) => (new DateChangeResource($entry))->resolve());

        $timeline = $statusEntries->concat($dateEntries)
            ->sortBy([['occurred_at', 'desc'], ['id', 'desc']])
            ->values();

        return response()->json(['data' => $timeline]);
    }
}
