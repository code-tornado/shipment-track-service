<?php

namespace App\Tracking;

use App\Enums\DateField;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Services\Actor;
use App\Services\DateChangeService;
use App\Services\ShipmentStatusService;
use Carbon\CarbonImmutable;

/**
 * Pulls updates from the configured provider and applies them through the
 * normal status / date services, so provider changes show up in the history
 * exactly like manual ones (source = provider).
 */
class TrackingSyncService
{
    public function __construct(
        private readonly TrackingProvider $provider,
        private readonly ShipmentStatusService $status,
        private readonly DateChangeService $dates,
    ) {}

    /**
     * @return array{status_changes: int, date_changes: int, delivery_changes: int}
     */
    public function syncShipment(Shipment $shipment): array
    {
        $actor = Actor::provider($this->provider->name());
        $summary = ['status_changes' => 0, 'date_changes' => 0, 'delivery_changes' => 0];

        $shipment->loadMissing('containers.cargoItems');

        $targetStatus = null;
        $eta = null;
        $ata = null;
        $note = null;

        foreach ($shipment->containers as $container) {
            $update = $this->provider->trackContainer($container);
            if ($update === null || $update->isEmpty()) {
                continue;
            }

            // A shipment is only as far along as its slowest container.
            if ($update->status !== null) {
                $targetStatus = $targetStatus === null || $update->status->order() < $targetStatus->order()
                    ? $update->status
                    : $targetStatus;
            }
            $eta = $this->latest($eta, $update->eta);
            $ata = $this->latest($ata, $update->ata);
            $note ??= $update->note;
        }

        $dateChanges = array_filter([
            DateField::Eta->value => $eta,
            DateField::Ata->value => $ata,
        ]);

        if ($dateChanges) {
            $changes = $this->dates->changeShipmentDates(
                $shipment, $dateChanges, 'Update from '.$this->provider->name().($note ? ': '.$note : ''), $actor
            );
            $summary['date_changes'] += count($changes);
        }

        if ($targetStatus !== null && $targetStatus->order() > $shipment->status->order()) {
            foreach (self::pathTo($shipment->status, $targetStatus) as $step) {
                $this->status->transition($shipment, $step, $actor, $note ?? 'Reported by '.$this->provider->name());
                $summary['status_changes']++;
            }
        }

        foreach ($shipment->containers as $container) {
            foreach ($container->cargoItems as $item) {
                $delivery = $this->provider->trackDelivery($item);
                if ($delivery === null || $delivery->isEmpty()) {
                    continue;
                }

                $changes = $this->dates->changeCargoItemDates(
                    $item,
                    array_filter([
                        DateField::CustomerDeliveryDate->value => $delivery->plannedDate,
                        DateField::DeliveredAt->value => $delivery->deliveredAt,
                    ]),
                    'Update from '.$this->provider->name().($delivery->note ? ': '.$delivery->note : ''),
                    $actor,
                );
                $summary['delivery_changes'] += count($changes);
            }
        }

        return $summary;
    }

    /**
     * Shortest sequence of allowed transitions from one status to another.
     *
     * @return list<ShipmentStatus>
     */
    public static function pathTo(ShipmentStatus $from, ShipmentStatus $to): array
    {
        $queue = [[$from, []]];
        $seen = [$from->value => true];

        while ($queue) {
            [$current, $path] = array_shift($queue);
            if ($current === $to) {
                return $path;
            }
            foreach ($current->allowedTransitions() as $next) {
                if (! isset($seen[$next->value])) {
                    $seen[$next->value] = true;
                    $queue[] = [$next, [...$path, $next]];
                }
            }
        }

        return [];
    }

    private function latest(?CarbonImmutable $a, ?CarbonImmutable $b): ?CarbonImmutable
    {
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }

        return $b->gt($a) ? $b : $a;
    }
}
