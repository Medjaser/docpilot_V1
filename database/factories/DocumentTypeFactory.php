<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentType> */
class DocumentTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->words(3, true),
            'is_mandatory' => true,
            'has_expiration_date' => true,
            'default_validity_months' => 12,
            'reminder_days' => [30, 7],
            'allowed_extensions' => ['pdf'],
            'max_file_size_mb' => 10,
            'is_active' => true,
        ];
    }
}
