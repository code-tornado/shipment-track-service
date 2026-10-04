<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CargoItemResource;
use App\Http\Resources\ContainerResource;
use App\Http\Resources\ShipmentSummaryResource;
use App\Models\CargoItem;
use App\Models\Container;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Find shipments and cargo by container no, proforma invoice, tag, product or customer.
 */
class LookupController extends Controller
{
    public const TYPES = ['all', 'container', 'invoice', 'tag', 'product', 'customer'];

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $term = trim($data['q']);
        $type = $data['type'] ?? 'all';
        $limit = (int) ($data['limit'] ?? 200);
        $like = '%'.$term.'%';
        $digits = preg_replace('/\D+/', '', $term);

        $containers = collect();
        if (in_array($type, ['all', 'container'], true)) {
            $containers = Container::query()
                ->with(['shipment', 'cargoItems.customer', 'cargoItems.product'])
                ->whereLike('container_no', $like)
                ->limit($limit)
                ->get();
        }

        $items = collect();
        if ($type !== 'container') {
            $items = CargoItem::query()
                ->with(['container.shipment', 'customer', 'product'])
                ->where(function ($q) use ($type, $like, $digits) {
                    if (in_array($type, ['all', 'invoice'], true)) {
                        $q->orWhereLike('proforma_invoice_no', $like)->orWhereLike('exporter_ref', $like);
                    }
                    if (in_array($type, ['all', 'tag'], true)) {
                        $q->orWhereLike('tag_no', $like)->orWhereLike('package_no', $like);
                        if ($digits !== '') {
                            $q->orWhereLike('tag_no', '%'.$digits.'%');
                        }
                    }
                    if (in_array($type, ['all', 'product'], true)) {
                        $q->orWhereHas('product', fn ($p) => $p->whereLike('description', $like)->orWhereLike('material_code', $like));
                    }
                    if (in_array($type, ['all', 'customer'], true)) {
                        $q->orWhereHas('customer', fn ($c) => $c->whereLike('name', $like));
                    }
                })
                ->orderByDesc('id')
                ->limit($limit)
                ->get();
        }

        $shipmentIds = $containers->pluck('shipment_id')
            ->merge($items->map(fn ($i) => $i->container->shipment_id))
            ->unique()
            ->values();

        $shipments = Shipment::query()
            ->with(['containers.cargoItems.customer'])
            ->whereIn('id', $shipmentIds)
            ->orderByDesc('etd')
            ->get();

        return response()->json([
            'query' => $term,
            'type' => $type,
            'shipments' => ShipmentSummaryResource::collection($shipments),
            'containers' => ContainerResource::collection($containers),
            'cargo_items' => CargoItemResource::collection($items),
        ]);
    }
}
