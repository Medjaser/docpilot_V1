<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateItem;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentTemplateItem> */
class DocumentTemplateItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'template_id' => DocumentTemplate::factory(),
            'document_type_id' => DocumentType::factory(),
            'is_required' => true,
            'position' => 0,
        ];
    }
}
