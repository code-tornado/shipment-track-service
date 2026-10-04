<?php

namespace Database\Factories;

use App\Enums\NetType;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'material_code' => fake()->unique()->numerify('454########'),
            'description' => 'NP-'.fake()->numberBetween(120, 180).'mCx1.3+18+15/KNXWht/360p-16.5HM',
            'net_type' => NetType::NetPen,
        ];
    }
}
