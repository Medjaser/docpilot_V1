<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Company;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\RequestedDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RequestedDocument> */
class RequestedDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'document_request_id' => DocumentRequest::factory(),
            'document_type_id' => DocumentType::factory(),
            'status' => DocumentStatus::Missing,
            'due_date' => now()->addDays(14),
        ];
    }
}
