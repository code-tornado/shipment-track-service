<?php

namespace App\Http\Resources;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Shipment
 */
class ShipmentSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->containers->flatMap->cargoItems;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'allowed_transitions' => array_map(fn (ShipmentStatus $s) => $s->value, $this->status->allowedTransitions()),
            'shipping_method' => $this->shipping_method->value,
            'shipping_line' => $this->shipping_line,
            'origin_port' => $this->origin_port,
            'destination_port' => $this->destination_port,
            'incoterm' => $this->incoterm,
            'etd' => $this->etd?->toDateString(),
            'eta' => $this->eta?->toDateString(),
            'ata' => $this->ata?->toDateString(),
            'days_delayed' => $this->transitDelayDays(),
            'is_delayed' => $this->isDelayed(),
            'status_changed_at' => $this->status_changed_at?->toIso8601String(),
            'notes' => $this->notes,
            'containers_count' => $this->containers->count(),
            'container_nos' => $this->containers->pluck('container_no')->all(),
            'cargo_items_count' => $items->count(),
            'delivered_items_count' => $items->filter->isDelivered()->count(),
            'customers' => $items->map(fn ($i) => $i->customer?->name)->filter()->unique()->values()->all(),
            'proforma_invoice_nos' => $items->pluck('proforma_invoice_no')->unique()->values()->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
