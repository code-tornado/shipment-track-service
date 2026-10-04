<?php

namespace App\Http\Resources;

use App\Models\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Container
 */
class ContainerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'container_no' => $this->container_no,
            'seal_no' => $this->seal_no,
            'container_type' => $this->container_type,
            'customs_cleared' => $this->customs_cleared,
            'storage_facility' => $this->storage_facility,
            'storage_date' => $this->storage_date?->toDateString(),
            'pickup_date' => $this->pickup_date?->toDateString(),
            'cargo_items_count' => $this->whenLoaded('cargoItems', fn () => $this->cargoItems->count()),
            'cargo_items' => CargoItemResource::collection($this->whenLoaded('cargoItems')),
            'shipment' => $this->whenLoaded('shipment', fn () => [
                'id' => $this->shipment->id,
                'reference' => $this->shipment->reference,
                'status' => $this->shipment->status->value,
                'status_label' => $this->shipment->status->label(),
                'etd' => $this->shipment->etd?->toDateString(),
                'eta' => $this->shipment->eta?->toDateString(),
                'ata' => $this->shipment->ata?->toDateString(),
                'destination_port' => $this->shipment->destination_port,
            ]),
        ];
    }
}
