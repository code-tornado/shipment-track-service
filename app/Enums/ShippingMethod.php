<?php

namespace App\Enums;

enum ShippingMethod: string
{
    case Sea = 'sea';
    case Air = 'air';
    case Road = 'road';

    public static function tryFromLabel(?string $label): ?self
    {
        return match (strtolower(trim((string) $label))) {
            'sea', 'ocean', 'ship', 'vessel' => self::Sea,
            'air', 'airfreight', 'air freight', 'flight' => self::Air,
            'road', 'truck', 'trailer' => self::Road,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $m) => $m->value, self::cases());
    }
}
