<?php

namespace App\Console\Commands;

use App\Enums\VerificationStatus;
use App\Models\TutorProfile;
use Database\Seeders\ShowcaseTutorSeeder;
use Illuminate\Console\Command;

class SeedShowcaseTutorsCommand extends Command
{
    protected $signature = 'tutors:seed-showcase';

    protected $description = 'Seed 25 realistic, verified Burewala showcase teachers (T-BW001 to T-BW025) safely across any environment';

    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => ShowcaseTutorSeeder::class,
            '--force' => true,
        ]);

        $count = TutorProfile::where('verification_status', VerificationStatus::Approved->value)->count();
        $this->info("Showcase tutors seeded successfully. Total Approved Tutors: {$count}");

        return self::SUCCESS;
    }
}
