<?php

namespace App\Enums;

/**
 * Product families as marked on the packing slip: NP (net pen) and DF
 * (dead fish collector) are the ones Suppli flags explicitly.
 */
enum NetType: string
{
    case NetPen = 'net_pen';
    case DeadFishCollector = 'dead_fish_collector';
    case LiceShield = 'lice_shield';
    case RepairPanel = 'repair_panel';
    case VelcroStraps = 'velcro_straps';
    case Rope = 'rope';
    case BirdNet = 'bird_net';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NetPen => 'Net pen (NP)',
            self::DeadFishCollector => 'Dead fish collector (DF)',
            self::LiceShield => 'Lice shield',
            self::RepairPanel => 'Repair panel',
            self::VelcroStraps => 'Velcro straps',
            self::Rope => 'Rope',
            self::BirdNet => 'Bird net',
            self::Other => 'Other',
        };
    }

    /**
     * Resolve from the Excel "Net type" column, falling back to the product
     * description prefix (NP-…, DFColle-…, RepairPanel-…).
     */
    public static function fromLabel(?string $label, ?string $description = null): self
    {
        $key = strtolower(trim((string) $label));
        $key = preg_replace('/[\s_\-]+/', ' ', $key);

        $byLabel = match ($key) {
            'net cage', 'net pen', 'np', 'cage' => self::NetPen,
            'dfc', 'df', 'dead fish collector', 'dead fish collect' => self::DeadFishCollector,
            'lice shield', 'liceshield' => self::LiceShield,
            'repair panel', 'repairpanel' => self::RepairPanel,
            'velcro straps', 'velcro strap', 'straps' => self::VelcroStraps,
            'rope', 'ropes' => self::Rope,
            'bird net', 'birdnet' => self::BirdNet,
            default => null,
        };

        if ($byLabel) {
            return $byLabel;
        }

        $desc = strtoupper((string) $description);

        return match (true) {
            str_starts_with($desc, 'NP-') => self::NetPen,
            str_starts_with($desc, 'DF') => self::DeadFishCollector,
            str_contains($desc, 'LICESHIELD') => self::LiceShield,
            str_starts_with($desc, 'REPAIRPANEL') => self::RepairPanel,
            str_contains($desc, 'VELCRO') => self::VelcroStraps,
            default => self::Other,
        };
    }
}
