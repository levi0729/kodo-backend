<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username'          => fake()->unique()->userName(),
            'email'             => fake()->unique()->safeEmail(),
            'display_name'      => fake()->name(),
            'job_title'         => fake()->jobTitle(),
            'department'        => fake()->randomElement(['Engineering', 'Design', 'QA', 'Management']),
            'password'          => static::$password ??= Hash::make('password'),
            'presence_status'   => 'offline',
            'is_active'         => true,
            'email_verified_at' => now(),
            'remember_token'    => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
