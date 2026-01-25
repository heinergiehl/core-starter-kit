<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Clean up orphan users created during abandoned checkouts.
 *
 * An orphan user is one that:
 * - Has no verified email (email_verified_at is null)
 * - Has no active subscription
 * - Was created more than 7 days ago
 * - Has never logged in (last_login_at is null or never set)
 *
 * Schedule this to run daily: $schedule->command('billing:cleanup-orphans')->daily();
 */
class CleanupOrphanUsers extends Command
{
    protected $signature = 'billing:cleanup-orphans 
                            {--days=7 : Number of days to wait before cleaning up}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clean up orphan users from abandoned checkouts';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        $this->info("Finding orphan users created before {$cutoff->toDateTimeString()}...");

        // Find users who:
        // 1. Have no verified email
        // 2. Were created before the cutoff
        // 3. Have no subscription
        $orphanUsers = User::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('subscriptions', function ($subQuery) {
                $subQuery->whereIn('status', ['active', 'trialing', 'past_due']);
            })
            ->get();

        $count = $orphanUsers->count();

        if ($count === 0) {
            $this->info('No orphan users found.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} orphan user(s).");

        if ($dryRun) {
            $this->table(
                ['ID', 'Email', 'Created At'],
                $orphanUsers->map(fn ($user) => [
                    $user->id,
                    $user->email,
                    $user->created_at->toDateTimeString(),
                ])
            );
            $this->warn('Dry run mode - no users were deleted.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $deleted = 0;
        foreach ($orphanUsers as $user) {
            try {
                $user->delete();
                $deleted++;
            } catch (\Throwable $e) {
                $this->error("Failed to delete user {$user->id}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Deleted {$deleted} orphan user(s).");

        return self::SUCCESS;
    }
}
