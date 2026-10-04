<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingMethod;
use App\Models\CargoItem;
use App\Models\Container;
use App\Models\Shipment;
use App\Rules\ContainerNumber;
use App\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates shipments, containers and cargo items from validated input.
 * Shared by the API and the Excel importer.
 */
class ShipmentService
{
    public function __construct(
        private readonly ShipmentStatusService $status,
        private readonly ReferenceResolver $references,
        private readonly CurrentCompany $current,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated shipment payload, optionally with "containers"
     */
    public function create(array $data, Actor $actor, ?CarbonImmutable $occurredAt = null): Shipment
    {
        return DB::transaction(function () use ($data, $actor, $occurredAt) {
            $etd = CarbonImmutable::parse($data['etd']);
            $eta = CarbonImmutable::parse($data['eta']);
            $ata = isset($data['ata']) ? CarbonImmutable::parse($data['ata']) : null;
            DateChangeService::assertConsistent($etd, $eta, $ata);

            $status = isset($data['status']) ? ShipmentStatus::from($data['status']) : ShipmentStatus::Planned;

            $shipment = Shipment::create([
                'reference' => $data['reference'] ?? Shipment::nextReference($this->current->id(), $etd->year),
                'status' => $status,
                'shipping_method' => ShippingMethod::from($data['shipping_method']),
                'shipping_line' => $data['shipping_line'] ?? null,
                'origin_port' => $data['origin_port'] ?? null,
                'destination_port' => $data['destination_port'],
                'incoterm' => $data['incoterm'] ?? null,
                'etd' => $etd,
                'eta' => $eta,
                'ata' => $ata,
                'status_changed_at' => $occurredAt ?? CarbonImmutable::now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->userId,
            ]);

            $this->status->recordInitial($shipment, $actor, $data['status_note'] ?? null, $occurredAt);

            foreach ($data['containers'] ?? [] as $containerData) {
                $this->addContainer($shipment, $containerData);
            }

            return $shipment->load('containers.cargoItems');
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated container payload, optionally with "cargo_items"
     */
    public function addContainer(Shipment $shipment, array $data): Container
    {
        $container = $shipment->containers()->create([
            'company_id' => $shipment->company_id,
            'container_no' => ContainerNumber::normalize($data['container_no'], $shipment->shipping_method),
            'seal_no' => $data['seal_no'] ?? null,
            'container_type' => $data['container_type'] ?? null,
            'customs_cleared' => (bool) ($data['customs_cleared'] ?? false),
            'storage_facility' => $data['storage_facility'] ?? null,
            'storage_date' => $data['storage_date'] ?? null,
            'pickup_date' => $data['pickup_date'] ?? null,
        ]);

        foreach ($data['cargo_items'] ?? [] as $itemData) {
            $this->addCargoItem($container, $itemData);
        }

        return $container;
    }

    /**
     * @param  array<string, mixed>  $data  validated cargo item payload
     */
    public function addCargoItem(Container $container, array $data): CargoItem
    {
        return $container->cargoItems()->create([
            'company_id' => $container->company_id,
            ...$this->cargoItemAttributes($data),
        ]);
    }

    /**
     * Map an API/importer payload to cargo item columns, resolving customer and product.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function cargoItemAttributes(array $data): array
    {
        $attributes = collect($data)->only([
            'proforma_invoice_no', 'exporter_ref', 'customer_po', 'tag_no', 'package_no', 'quantity', 'unit',
            'net_weight_kg', 'gross_weight_kg', 'is_stock', 'certificate_sent', 'customer_delivery_date',
            'delivered_at', 'comments',
        ])->all();

        if (isset($data['customer_id'])) {
            $attributes['customer_id'] = $data['customer_id'];
        } elseif (isset($data['customer_name'])) {
            $attributes['customer_id'] = $this->references->customer($data['customer_name'])->id;
        }

        if (isset($data['product_id'])) {
            $attributes['product_id'] = $data['product_id'];
        } elseif (isset($data['product'])) {
            $attributes['product_id'] = $this->references->product(
                $data['product']['material_code'] ?? null,
                $data['product']['description'],
                $data['product']['net_type'] ?? null,
            )->id;
        }

        return $attributes;
    }
}
