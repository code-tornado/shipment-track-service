<?php

namespace App\Enums;

/** Dates owned by this service whose changes are written to the audit trail. */
enum DateField: string
{
    case Etd = 'etd';
    case Eta = 'eta';
    case Ata = 'ata';
    case CustomerDeliveryDate = 'customer_delivery_date';
    case DeliveredAt = 'delivered_at';

    public function label(): string
    {
        return match ($this) {
            self::Etd => 'ETD',
            self::Eta => 'ETA',
            self::Ata => 'Actual arrival',
            self::CustomerDeliveryDate => 'Customer delivery date',
            self::DeliveredAt => 'Delivered at',
        };
    }

    /**
     * @return list<self>
     */
    public static function forShipment(): array
    {
        return [self::Etd, self::Eta, self::Ata];
    }

    /**
     * @return list<self>
     */
    public static function forCargoItem(): array
    {
        return [self::CustomerDeliveryDate, self::DeliveredAt];
    }
}
