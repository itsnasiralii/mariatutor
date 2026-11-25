<?php

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TutorProfile>
 */
class TutorProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->tutor(),
            'tutor_code' => 'T-BW'.fake()->unique()->numerify('####'),
            'full_name' => fake()->name(),
            'gender' => 'Male',
            'cnic_gender_hint' => 'Male',
            'qualification' => 'M.Sc Mathematics',
            'experience' => '4 years home tuition experience',
            'expected_fee_min' => 10000,
            'expected_fee_max' => 18000,
            'availability' => '4:00 PM - 8:00 PM',
            'verification_status' => VerificationStatus::Pending->value,
            'verification_note' => null,
            'approved_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'verification_status' => VerificationStatus::Approved->value,
            'approved_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'verification_status' => VerificationStatus::Pending->value,
            'approved_at' => null,
        ]);
    }

    public function female(): static
    {
        return $this->state(fn () => [
            'gender' => 'Female',
            'cnic_gender_hint' => 'Female',
        ]);
    }

    public function male(): static
    {
        return $this->state(fn () => [
            'gender' => 'Male',
            'cnic_gender_hint' => 'Male',
        ]);
    }
}
