<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\Company;
use App\Models\DocumentRequest;
use App\Models\Subcontractor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DocumentRequest> */
class DocumentRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'subcontractor_id' => Subcontractor::factory(),
            'token_hash' => hash('sha256', Str::random(48)),
            'status' => RequestStatus::Sent,
            'expires_at' => now()->addDays(30),
            'auto_reminders' => true,
        ];
    }
}
