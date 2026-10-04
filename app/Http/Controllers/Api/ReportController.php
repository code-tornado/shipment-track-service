<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CargoItemResource;
use App\Http\Resources\ShipmentSummaryResource;
use App\Models\CargoItem;
use App\Models\Shipment;
use App\Queries\ShipmentQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Customer deliveries due in a date range (default: next 14 days), plus
     * shipments expected to arrive at the port in the same range.
     */
    public function upcomingDeliveries(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'])->startOfDay() : CarbonImmutable::today();
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'])->startOfDay() : $from->addDays(14);

        $deliveries = CargoItem::query()
            ->with(['container.shipment', 'customer', 'product'])
            ->whereNull('delivered_at')
            ->whereDate('customer_delivery_date', '>=', $from)
            ->whereDate('customer_delivery_date', '<=', $to)
            ->when($data['customer_id'] ?? null, fn ($q, $id) => $q->where('customer_id', $id))
            ->orderBy('customer_delivery_date')
            ->orderBy('id')
            ->get();

        $arrivals = Shipment::query()
            ->with(['containers.cargoItems.customer'])
            ->whereIn('status', [ShipmentStatus::Planned, ShipmentStatus::InTransit])
            ->whereDate('eta', '>=', $from)
            ->whereDate('eta', '<=', $to)
            ->orderBy('eta')
            ->get();

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => [
                'deliveries' => $deliveries->count(),
                'arrivals' => $arrivals->count(),
            ],
            'deliveries' => CargoItemResource::collection($deliveries),
            'arrivals' => ShipmentSummaryResource::collection($arrivals),
        ]);
    }

    /**
     * How many shipments, containers and cargo items are in each status, per customer too.
     */
    public function statusOverview(): JsonResponse
    {
        $shipments = Shipment::query()->with(['containers.cargoItems.customer'])->get();
        $today = CarbonImmutable::today();

        $empty = array_fill_keys(ShipmentStatus::values(), 0);
        $byStatus = ['shipments' => $empty, 'containers' => $empty, 'cargo_items' => $empty];
        $byCustomer = [];

        foreach ($shipments as $shipment) {
            $status = $shipment->status->value;
            $byStatus['shipments'][$status]++;

            foreach ($shipment->containers as $container) {
                $byStatus['containers'][$status]++;

                foreach ($container->cargoItems as $item) {
                    $itemStatus = $item->derivedStatus($shipment->status)->value;
                    $byStatus['cargo_items'][$itemStatus]++;

                    $name = $item->customer?->name ?? 'Unknown';
                    $byCustomer[$name] ??= ['customer' => $name, 'cargo_items' => 0, ...$empty];
                    $byCustomer[$name]['cargo_items']++;
                    $byCustomer[$name][$itemStatus]++;
                }
            }
        }

        ksort($byCustomer);

        $delayedShipments = $shipments->filter(fn (Shipment $s) => $s->isDelayed($today));
        $lateDeliveries = ShipmentQuery::lateDeliveries(CargoItem::query(), $today)->count();
        $upcoming = CargoItem::query()
            ->whereNull('delivered_at')
            ->whereDate('customer_delivery_date', '>=', $today)
            ->whereDate('customer_delivery_date', '<=', $today->addDays(7))
            ->count();

        return response()->json([
            'as_of' => $today->toDateString(),
            'statuses' => array_map(fn (ShipmentStatus $s) => ['value' => $s->value, 'label' => $s->label()], ShipmentStatus::cases()),
            'by_status' => $byStatus,
            'by_customer' => array_values($byCustomer),
            'highlights' => [
                'shipments_total' => $shipments->count(),
                'shipments_delayed' => $delayedShipments->count(),
                'shipments_in_transit' => $byStatus['shipments'][ShipmentStatus::InTransit->value],
                'arriving_next_7_days' => $shipments->filter(fn (Shipment $s) => ! $s->hasArrived() && $s->eta->between($today, $today->addDays(7)))->count(),
                'cargo_items_in_storage' => $byStatus['cargo_items'][ShipmentStatus::InStorage->value],
                'deliveries_next_7_days' => $upcoming,
                'deliveries_late' => $lateDeliveries,
            ],
        ]);
    }

    /**
     * Shipments that arrived late or are overdue, and cargo items delivered late or overdue.
     */
    public function delayed(): JsonResponse
    {
        $today = CarbonImmutable::today();

        $shipments = ShipmentQuery::delayed(Shipment::query()->with(['containers.cargoItems.customer']), $today)
            ->get()
            ->sortByDesc(fn (Shipment $s) => $s->transitDelayDays($today))
            ->values();

        $items = ShipmentQuery::lateDeliveries(CargoItem::query()->with(['container.shipment', 'customer', 'product']), $today)
            ->get()
            ->sortByDesc(fn (CargoItem $i) => $i->deliveryDelayDays($today))
            ->values();

        return response()->json([
            'as_of' => $today->toDateString(),
            'totals' => [
                'shipments' => $shipments->count(),
                'cargo_items' => $items->count(),
            ],
            'shipments' => ShipmentSummaryResource::collection($shipments),
            'cargo_items' => CargoItemResource::collection($items),
        ]);
    }
}
