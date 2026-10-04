<?php

namespace Database\Factories;

use App\Models\Container;
use App\Models\Shipment;
use App\Rules\ContainerNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Container>
 */
class ContainerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'company_id' => fn (array $attributes) => Shipment::withoutGlobalScopes()->find($attributes['shipment_id'])->company_id,
            'container_no' => self::validContainerNumber(),
            'seal_no' => fake()->optional()->bothify('SL######'),
            'container_type' => fake()->randomElement(['45UT', '45GP', '40OT', '22UT']),
            'customs_cleared' => false,
        ];
    }

    /** Generate a random ISO 6346 number with a correct check digit. */
    public static function validContainerNumber(): string
    {
        $owner = fake()->randomElement(['HLBU', 'MEDU', 'HAMU', 'TRHU', 'UETU', 'QIBU']);
        $serial = fake()->numerify('######');
        $body = $owner.$serial;

        return $body.ContainerNumber::iso6346CheckDigit($body);
    }
}
