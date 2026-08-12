<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->uuid().'@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => UserRole::Candidate,
            'status' => UserStatus::Active,
            'locale' => 'tr',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Company,
        ]);
    }
}
