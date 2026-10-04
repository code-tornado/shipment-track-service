<?php

namespace App\Services;

use App\Enums\DateField;
use App\Events\CargoItemDatesChanged;
use App\Events\ShipmentDatesChanged;
use App\Exceptions\InvalidDates;
use App\Models\CargoItem;
use App\Models\DateChange;
use App\Models\Shipment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only place the dates this service owns change. Validates the result
 * (ETD ≤ ETA, arrival not before departure) and writes one audit row per
 * changed field: old value, new value, who, when, why.
 */
class DateChangeService
{
    /**
     * @param  array<string, CarbonImmutable|string|null>  $dates  field => new value
     * @return list<DateChange>
     */
    public function changeShipmentDates(
        Shipment $shipment,
        array $dates,
        string $reason,
        Actor $actor,
        ?CarbonImmutable $occurredAt = null,
    ): array {
        $dates = $this->normalize($dates, DateField::forShipment());

        $etd = array_key_exists('etd', $dates) ? $dates['etd'] : $shipment->etd;
        $eta = array_key_exists('eta', $dates) ? $dates['eta'] : $shipment->eta;
        $ata = array_key_exists('ata', $dates) ? $dates['ata'] : $shipment->ata;

        self::assertConsistent($etd, $eta, $ata);

        return DB::transaction(function () use ($shipment, $dates, $reason, $actor, $occurredAt) {
            $changes = $this->apply($shipment, $dates, $reason, $actor, $occurredAt ?? CarbonImmutable::now());

            if ($changes) {
                $shipment->save();
                ShipmentDatesChanged::dispatch($shipment, $changes);
            }

            return $changes;
        });
    }

    /**
     * @param  array<string, CarbonImmutable|string|null>  $dates  field => new value
     * @return list<DateChange>
     */
    public function changeCargoItemDates(
        CargoItem $item,
        array $dates,
        string $reason,
        Actor $actor,
        ?CarbonImmutable $occurredAt = null,
    ): array {
        $dates = $this->normalize($dates, DateField::forCargoItem());

        return DB::transaction(function () use ($item, $dates, $reason, $actor, $occurredAt) {
            $changes = $this->apply($item, $dates, $reason, $actor, $occurredAt ?? CarbonImmutable::now());

            if ($changes) {
                $item->save();
                CargoItemDatesChanged::dispatch($item, $changes);
            }

            return $changes;
        });
    }

    public static function assertConsistent(?CarbonImmutable $etd, ?CarbonImmutable $eta, ?CarbonImmutable $ata): void
    {
        $errors = [];

        if ($etd && $eta && $eta->lt($etd)) {
            $errors['eta'][] = 'ETA must be on or after ETD.';
        }
        if ($etd && $ata && $ata->lt($etd)) {
            $errors['ata'][] = 'Actual arrival must be on or after ETD.';
        }

        if ($errors) {
            throw new InvalidDates('The dates are inconsistent.', $errors);
        }
    }

    /**
     * @param  array<string, CarbonImmutable|string|null>  $dates
     * @param  list<DateField>  $allowed
     * @return array<string, CarbonImmutable|null>
     */
    private function normalize(array $dates, array $allowed): array
    {
        $allowedKeys = array_map(fn (DateField $f) => $f->value, $allowed);
        $out = [];

        foreach ($dates as $field => $value) {
            if (! in_array($field, $allowedKeys, true)) {
                throw new InvalidDates("Unknown date field [{$field}].", [$field => ['Unknown date field.']]);
            }
            $out[$field] = $value === null || $value === ''
                ? null
                : CarbonImmutable::parse($value)->startOfDay();
        }

        return $out;
    }

    /**
     * @param  array<string, CarbonImmutable|null>  $dates
     * @return list<DateChange>
     */
    private function apply(Model $subject, array $dates, string $reason, Actor $actor, CarbonImmutable $occurredAt): array
    {
        $changes = [];

        foreach ($dates as $field => $new) {
            /** @var CarbonImmutable|null $old */
            $old = $subject->{$field};

            if (($old?->toDateString()) === ($new?->toDateString())) {
                continue;
            }

            $subject->{$field} = $new;

            $changes[] = $subject->dateChanges()->create([
                'company_id' => $subject->company_id,
                'field' => $field,
                'old_value' => $old,
                'new_value' => $new,
                'reason' => $reason,
                'source' => $actor->source,
                'actor_id' => $actor->userId,
                'actor_label' => $actor->label,
                'occurred_at' => $occurredAt,
            ]);
        }

        return $changes;
    }
}
