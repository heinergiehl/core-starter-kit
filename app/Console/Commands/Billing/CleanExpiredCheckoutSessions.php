<?php

namespace App\Console\Commands\Billing;

use App\Domain\Billing\Models\CheckoutSession;
use Illuminate\Console\Command;

class CleanExpiredCheckoutSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:clean-sessions {--days=7 : Number of days after which to delete expired sessions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired checkout sessions older than specified days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');

        $this->info("Cleaning checkout sessions older than {$days} days...");

        $deleted = CheckoutSession::query()
            ->where('expires_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Deleted {$deleted} expired checkout sessions.");

        return Command::SUCCESS;
    }
}
