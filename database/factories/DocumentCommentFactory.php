<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentComment> */
class DocumentCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'document_id' => Document::factory(),
            'author_type' => 'user',
            'author_id' => null,
            'body' => fake()->sentence(),
            'visibility' => DocumentComment::VISIBILITY_INTERNAL,
        ];
    }
}
