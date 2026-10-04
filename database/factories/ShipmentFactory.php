<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingMethod;
use App\Models\Company;
use App\Models\Shipment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        $etd = CarbonImmutable::today()->subDays(fake()->numberBetween(10, 60));

        return [
            'company_id' => Company::factory(),
            'reference' => 'SHP-'.$etd->year.'-'.fake()->unique()->numerify('####'),
            'status' => ShipmentStatus::InTransit,
            'shipping_method' => ShippingMethod::Sea,
            'shipping_line' => 'TRANSSEA AS',
            'origin_port' => 'Nhava Sheva',
            'destination_port' => fake()->randomElement(['BERGEN', 'ORKANGER', 'MÅLØY', 'SALTEN']),
            'incoterm' => 'CIF',
            'etd' => $etd,
            'eta' => $etd->addDays(45),
            'ata' => null,
            'status_changed_at' => $etd,
        ];
    }

    public function status(ShipmentStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'ata' => $status->isAtLeast(ShipmentStatus::Arrived)
                ? CarbonImmutable::parse($attributes['eta'])
                : null,
        ]);
    }
}
