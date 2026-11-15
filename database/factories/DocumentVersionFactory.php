<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentVersion> */
class DocumentVersionFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->uuid().'.pdf';

        return [
            'company_id' => Company::factory(),
            'document_id' => Document::factory(),
            'version_number' => 1,
            'file_name' => $name,
            'original_name' => 'document.pdf',
            'file_path' => 'documents/'.$name,
            'disk' => 'documents',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'sha256' => hash('sha256', $name),
            'status' => DocumentStatus::Pending,
        ];
    }
}
