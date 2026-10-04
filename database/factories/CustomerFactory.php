<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->randomElement(['Fisk AS West', 'Fisk ASA North', 'Fisk AS Mid', 'Finnmark AS', 'Happy Laks AS', 'Tau AS']).' '.fake()->unique()->numerify('##'),
        ];
    }
}
