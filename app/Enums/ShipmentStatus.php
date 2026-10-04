<?php

namespace App\Enums;

/**
 * Status flow of a shipment:
 *
 *   planned → in_transit → arrived → in_storage → delivered
 *                 └──────────────────────┘ (storage date known, arrival not logged separately)
 *                             └─────────────────────┘ (delivered straight from the port)
 */
enum ShipmentStatus: string
{
    case Planned = 'planned';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case InStorage = 'in_storage';
    case Delivered = 'delivered';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Planned => [self::InTransit],
            self::InTransit => [self::Arrived, self::InStorage],
            self::Arrived => [self::InStorage, self::Delivered],
            self::InStorage => [self::Delivered],
            self::Delivered => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Position in the flow, used to compare how far along a shipment is. */
    public function order(): int
    {
        return match ($this) {
            self::Planned => 0,
            self::InTransit => 1,
            self::Arrived => 2,
            self::InStorage => 3,
            self::Delivered => 4,
        };
    }

    public function isAtLeast(self $other): bool
    {
        return $this->order() >= $other->order();
    }

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::InTransit => 'In transit',
            self::Arrived => 'Arrived',
            self::InStorage => 'In storage',
            self::Delivered => 'Delivered',
        };
    }

    /**
     * Map a free-text status, as typed into the Excel sheet, to a status.
     * "In Stock" is physically the same as "In Storage"; the importer keeps
     * the distinction on the cargo item (is_stock).
     */
    public static function tryFromLabel(?string $label): ?self
    {
        $key = strtolower(trim((string) $label));
        $key = preg_replace('/[\s_\-]+/', ' ', $key);

        return match ($key) {
            'planned', 'booked' => self::Planned,
            'in transit', 'transit', 'shipped' => self::InTransit,
            'arrived', 'at port' => self::Arrived,
            'in storage', 'storage', 'in stock', 'stock' => self::InStorage,
            'delivered' => self::Delivered,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
