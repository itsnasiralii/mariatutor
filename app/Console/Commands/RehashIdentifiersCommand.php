<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SecurityService;
use Illuminate\Console\Command;

class RehashIdentifiersCommand extends Command
{
    protected $signature = 'security:rehash-identifiers {--dry-run : Preview affected records without modifying the database}';

    protected $description = 'Re-hash stored email and phone identifier_hash values using the current CNIC_HASH_KEY';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $cnicOnly = 0;

        User::chunkById(100, function ($users) use ($dryRun, &$updated, &$cnicOnly): void {
            foreach ($users as $user) {
                if ($user->identifier_type === 'email' && ! empty($user->email)) {
                    $newHash = SecurityService::hashIdentifier($user->email, 'email');
                    if ($newHash !== $user->identifier_hash) {
                        if (! $dryRun) {
                            $user->update(['identifier_hash' => $newHash]);
                        }
                        $updated++;
                    }
                } elseif ($user->identifier_type === 'phone' && ! empty($user->phone)) {
                    $newHash = SecurityService::hashIdentifier($user->phone, 'phone');
                    if ($newHash !== $user->identifier_hash) {
                        if (! $dryRun) {
                            $user->update(['identifier_hash' => $newHash]);
                        }
                        $updated++;
                    }
                } elseif (! empty($user->cnic_hash)) {
                    $cnicOnly++;
                }
            }
        });

        $prefix = $dryRun ? '[DRY RUN] Would update' : 'Updated';
        $this->info("{$prefix} {$updated} user identifier hashes.");

        if ($cnicOnly > 0) {
            $this->warn("{$cnicOnly} user(s) have one-way CNIC hashes (raw CNICs are never stored). Those users can still sign in via email, phone, or tutor code.");
        }

        return self::SUCCESS;
    }
}
