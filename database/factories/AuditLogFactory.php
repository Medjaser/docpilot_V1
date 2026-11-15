<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_type' => 'system',
            'action' => 'test.action',
            'metadata' => ['source' => 'factory'],
            'ip_address' => fake()->ipv4(),
        ];
    }
}
