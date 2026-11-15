<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DatabaseNotification> */
class DatabaseNotificationFactory extends Factory
{
    protected $model = DatabaseNotification::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'company_id' => Company::factory(),
            'type' => 'test.notification',
            'notifiable_type' => 'user',
            'notifiable_id' => User::factory(),
            'data' => ['title' => fake()->sentence(3)],
        ];
    }
}
