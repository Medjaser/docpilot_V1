<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<NotificationLog> */
class NotificationLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'channel' => 'mail',
            'type' => 'test',
            'recipient' => fake()->safeEmail(),
            'dedup_key' => 'test:'.Str::uuid(),
            'sent_at' => now(),
        ];
    }
}
