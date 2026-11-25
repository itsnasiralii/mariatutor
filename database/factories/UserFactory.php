<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\SecurityService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $email = strtolower(fake()->unique()->safeEmail());

        return [
            'identifier_hash' => SecurityService::hashIdentifier($email, 'email'),
            'identifier_type' => 'email',
            'cnic_hash' => null,
            'name' => fake()->name(),
            'email' => $email,
            'phone' => '0300'.fake()->unique()->numerify('#######'),
            'role' => UserRole::Parent->value,
            'password' => static::$password ??= Hash::make('SecurePass123'),
            'active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::SuperAdmin->value,
            'permissions_json' => json_encode(['all' => true]),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Admin->value,
            'permissions_json' => json_encode(['verify_tutors' => true, 'manage_requests' => true]),
        ]);
    }

    public function tutor(): static
    {
        return $this->state(function () {
            $cnic = '36402-'.fake()->unique()->numerify('#######').'-1';

            return [
                'role' => UserRole::Tutor->value,
                'cnic_hash' => SecurityService::hashIdentifier($cnic, 'cnic'),
            ];
        });
    }

    public function parentRole(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Parent->value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'active' => false,
        ]);
    }
}
