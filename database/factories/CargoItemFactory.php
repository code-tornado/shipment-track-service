<?php

namespace Database\Factories;

use App\Models\CargoItem;
use App\Models\Container;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CargoItem>
 */
class CargoItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'container_id' => Container::factory(),
            'company_id' => fn (array $attributes) => Container::withoutGlobalScopes()->find($attributes['container_id'])->company_id,
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['company_id' => $attributes['company_id']])->id,
            'product_id' => fn (array $attributes) => Product::factory()->create(['company_id' => $attributes['company_id']])->id,
            'proforma_invoice_no' => fake()->numerify('8626#####'),
            'exporter_ref' => fake()->numerify('4626#####'),
            'customer_po' => fake()->bothify('RW#####'),
            'tag_no' => 'GNP-'.fake()->unique()->numerify('26#####'),
            'package_no' => 'WENA'.fake()->unique()->numerify('######'),
            'quantity' => 1,
            'unit' => 'PC',
            'net_weight_kg' => fake()->numberBetween(2000, 6000),
            'gross_weight_kg' => fn (array $attributes) => $attributes['net_weight_kg'] + 100,
            'is_stock' => false,
            'certificate_sent' => false,
        ];
    }
}
