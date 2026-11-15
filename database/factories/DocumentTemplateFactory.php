<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentTemplate> */
class DocumentTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
