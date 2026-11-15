<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Company;
use App\Models\UserInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<UserInvitation> */
class UserInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Manager,
            'token_hash' => hash('sha256', Str::random(48)),
            'expires_at' => now()->addDays(7),
        ];
    }
}
