<?php

namespace App\Http\Resources;

use App\Models\CargoItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CargoItem
 */
class CargoItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $shipment = $this->relationLoaded('container') && $this->container->relationLoaded('shipment')
            ? $this->container->shipment
            : null;

        $status = $this->derivedStatus($shipment?->status);

        return [
            'id' => $this->id,
            'container_id' => $this->container_id,
            'container_no' => $this->whenLoaded('container', fn () => $this->container->container_no),
            'shipment' => $this->when($shipment !== null, fn () => [
                'id' => $shipment->id,
                'reference' => $shipment->reference,
                'status' => $shipment->status->value,
                'status_label' => $shipment->status->label(),
                'etd' => $shipment->etd?->toDateString(),
                'eta' => $shipment->eta?->toDateString(),
                'ata' => $shipment->ata?->toDateString(),
                'destination_port' => $shipment->destination_port,
            ]),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'material_code' => $this->product->material_code,
                'description' => $this->product->description,
                'net_type' => $this->product->net_type->value,
                'net_type_label' => $this->product->net_type->label(),
            ]),
            'proforma_invoice_no' => $this->proforma_invoice_no,
            'exporter_ref' => $this->exporter_ref,
            'customer_po' => $this->customer_po,
            'tag_no' => $this->tag_no,
            'package_no' => $this->package_no,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'net_weight_kg' => $this->net_weight_kg !== null ? (float) $this->net_weight_kg : null,
            'gross_weight_kg' => $this->gross_weight_kg !== null ? (float) $this->gross_weight_kg : null,
            'is_stock' => $this->is_stock,
            'certificate_sent' => $this->certificate_sent,
            'status' => $status->value,
            'status_label' => $status->label(),
            'customer_delivery_date' => $this->customer_delivery_date?->toDateString(),
            'delivered_at' => $this->delivered_at?->toDateString(),
            'days_delayed' => $this->deliveryDelayDays(),
            'comments' => $this->comments,
            'date_changes' => DateChangeResource::collection($this->whenLoaded('dateChanges')),
        ];
    }
}
