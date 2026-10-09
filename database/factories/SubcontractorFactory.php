<?php

namespace Database\Factories;

use App\Enums\SubcontractorStatus;
use App\Models\Company;
use App\Models\Subcontractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subcontractor> */
class SubcontractorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'legal_name' => fake()->company(),
            'activity' => fake()->randomElement(['Électricité', 'Plomberie', 'Peinture', 'Isolation', 'Chauffage']),
            'contact_first_name' => fake()->firstName(),
            'contact_last_name' => fake()->lastName(),
            'contact_email' => fake()->unique()->safeEmail(),
            'city' => fake()->city(),
            'country' => 'FR',
            'status' => SubcontractorStatus::Draft,
        ];
    }
}
