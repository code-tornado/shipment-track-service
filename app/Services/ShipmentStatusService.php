<?php

namespace App\Services;

use App\Enums\DateField;
use App\Enums\ShipmentStatus;
use App\Events\ShipmentStatusChanged;
use App\Exceptions\InvalidStatusTransition;
use App\Models\Shipment;
use App\Models\StatusHistory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The only place a shipment's status changes. Enforces the status flow,
 * records history, and fills in the dates implied by a status (arrival date
 * on "arrived", storage date on "in storage", delivered date on "delivered").
 */
class ShipmentStatusService
{
    public function __construct(private readonly DateChangeService $dates) {}

    public function transition(
        Shipment $shipment,
        ShipmentStatus $to,
        Actor $actor,
        ?string $note = null,
        ?CarbonImmutable $occurredAt = null,
    ): Shipment {
        $from = $shipment->status;

        if (! $from->canTransitionTo($to)) {
            throw new InvalidStatusTransition($from, $to);
        }

        $occurredAt ??= CarbonImmutable::now();

        return DB::transaction(function () use ($shipment, $from, $to, $actor, $note, $occurredAt) {
            $this->applyImpliedDates($shipment, $to, $actor, $occurredAt);

            $shipment->status = $to;
            $shipment->status_changed_at = $occurredAt;
            $shipment->save();

            $entry = $this->record($shipment, $from, $to, $actor, $note, $occurredAt);

            ShipmentStatusChanged::dispatch($shipment, $entry);

            return $shipment;
        });
    }

    /** First history row when a shipment is created. */
    public function recordInitial(Shipment $shipment, Actor $actor, ?string $note = null, ?CarbonImmutable $occurredAt = null): StatusHistory
    {
        return $this->record($shipment, null, $shipment->status, $actor, $note, $occurredAt ?? CarbonImmutable::now());
    }

    private function record(
        Shipment $shipment,
        ?ShipmentStatus $from,
        ShipmentStatus $to,
        Actor $actor,
        ?string $note,
        CarbonImmutable $occurredAt,
    ): StatusHistory {
        return $shipment->statusHistory()->create([
            'company_id' => $shipment->company_id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'source' => $actor->source,
            'actor_id' => $actor->userId,
            'actor_label' => $actor->label,
            'occurred_at' => $occurredAt,
        ]);
    }

    private function applyImpliedDates(Shipment $shipment, ShipmentStatus $to, Actor $actor, CarbonImmutable $occurredAt): void
    {
        $date = $occurredAt->toImmutable()->startOfDay();

        if ($to->isAtLeast(ShipmentStatus::Arrived) && $shipment->ata === null) {
            $this->dates->changeShipmentDates(
                $shipment, [DateField::Ata->value => $date], 'Set when status changed to '.$to->label(), $actor, $occurredAt
            );
        }

        if ($to === ShipmentStatus::InStorage) {
            foreach ($shipment->containers as $container) {
                if ($container->storage_date === null) {
                    $container->storage_date = $date;
                    $container->save();
                }
            }
        }

        if ($to === ShipmentStatus::Delivered) {
            foreach ($shipment->cargoItems()->whereNull('delivered_at')->get() as $item) {
                $this->dates->changeCargoItemDates(
                    $item, [DateField::DeliveredAt->value => $date], 'Shipment marked as delivered', $actor, $occurredAt
                );
            }
        }
    }
}
