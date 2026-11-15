<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Subcontractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'subcontractor_id' => Subcontractor::factory(),
            'document_type_id' => DocumentType::factory(),
            'status' => DocumentStatus::Missing,
        ];
    }
}
