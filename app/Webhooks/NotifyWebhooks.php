<?php

namespace App\Webhooks;

use App\Events\CargoItemDatesChanged;
use App\Events\ShipmentDatesChanged;
use App\Events\ShipmentStatusChanged;
use App\Models\CargoItem;
use App\Models\DateChange;
use App\Models\Shipment;

/**
 * Turns domain events into webhook payloads. This is how Suppli learns about
 * status and date changes without polling.
 */
class NotifyWebhooks
{
    public const EVENT_STATUS = 'shipment.status_changed';

    public const EVENT_SHIPMENT_DATES = 'shipment.dates_changed';

    public const EVENT_ITEM_DATES = 'cargo_item.dates_changed';

    public function __construct(private readonly WebhookDispatcher $dispatcher) {}

    public function handleStatusChanged(ShipmentStatusChanged $event): void
    {
        $entry = $event->entry;

        $this->dispatcher->dispatch($event->shipment->company_id, self::EVENT_STATUS, [
            'shipment' => $this->shipment($event->shipment),
            'change' => [
                'from' => $entry->from_status?->value,
                'to' => $entry->to_status->value,
                'note' => $entry->note,
                'actor' => $entry->actor_label,
                'source' => $entry->source->value,
            ],
        ], $entry->occurred_at);
    }

    public function handleShipmentDatesChanged(ShipmentDatesChanged $event): void
    {
        $this->dispatcher->dispatch($event->shipment->company_id, self::EVENT_SHIPMENT_DATES, [
            'shipment' => $this->shipment($event->shipment),
            'changes' => array_map(fn (DateChange $c) => $this->change($c), $event->changes),
        ], $event->changes[0]->occurred_at ?? null);
    }

    public function handleCargoItemDatesChanged(CargoItemDatesChanged $event): void
    {
        $item = $event->item;
        $item->loadMissing('container.shipment', 'customer', 'product');

        $this->dispatcher->dispatch($item->company_id, self::EVENT_ITEM_DATES, [
            'cargo_item' => $this->item($item),
            'shipment' => $this->shipment($item->container->shipment),
            'changes' => array_map(fn (DateChange $c) => $this->change($c), $event->changes),
        ], $event->changes[0]->occurred_at ?? null);
    }

    private function shipment(Shipment $shipment): array
    {
        $shipment->loadMissing('containers.cargoItems');

        return [
            'id' => $shipment->id,
            'reference' => $shipment->reference,
            'status' => $shipment->status->value,
            'etd' => $shipment->etd?->toDateString(),
            'eta' => $shipment->eta?->toDateString(),
            'ata' => $shipment->ata?->toDateString(),
            'days_delayed' => $shipment->transitDelayDays(),
            'containers' => $shipment->containers->map(fn ($c) => [
                'container_no' => $c->container_no,
                'proforma_invoice_nos' => $c->cargoItems->pluck('proforma_invoice_no')->unique()->values()->all(),
                'tag_nos' => $c->cargoItems->pluck('tag_no')->filter()->unique()->values()->all(),
            ])->all(),
        ];
    }

    private function item(CargoItem $item): array
    {
        return [
            'id' => $item->id,
            'proforma_invoice_no' => $item->proforma_invoice_no,
            'exporter_ref' => $item->exporter_ref,
            'tag_no' => $item->tag_no,
            'package_no' => $item->package_no,
            'container_no' => $item->container->container_no,
            'customer' => $item->customer?->name,
            'product' => $item->product?->description,
            'customer_delivery_date' => $item->customer_delivery_date?->toDateString(),
            'delivered_at' => $item->delivered_at?->toDateString(),
        ];
    }

    private function change(DateChange $change): array
    {
        return [
            'field' => $change->field->value,
            'old' => $change->old_value?->toDateString(),
            'new' => $change->new_value?->toDateString(),
            'reason' => $change->reason,
            'actor' => $change->actor_label,
            'source' => $change->source->value,
        ];
    }
}
