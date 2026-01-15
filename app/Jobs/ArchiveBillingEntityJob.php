<?php

namespace App\Jobs;

use App\Domain\Billing\Services\BillingProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ArchiveBillingEntityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $provider,
        public string $type,
        public string $providerId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(BillingProviderManager $billing): void
    {
        // Extend execution time to prevent timeouts during bulk actions (per job)
        set_time_limit(120);

        try {
            $adapter = $billing->adapter($this->provider);

            if ($this->type === 'product') {
                Log::info("[ArchiveBillingEntityJob] STARTING archive Product {$this->providerId} on {$this->provider}");
                $adapter->archiveProduct($this->providerId);
                Log::info("[ArchiveBillingEntityJob] SUCCESS Archived Product {$this->providerId} on {$this->provider}");
            } elseif ($this->type === 'price') {
                Log::info("[ArchiveBillingEntityJob] STARTING archive Price {$this->providerId} on {$this->provider}");
                $adapter->archivePrice($this->providerId);
                Log::info("[ArchiveBillingEntityJob] SUCCESS Archived Price {$this->providerId} on {$this->provider}");
            } else {
                Log::warning("[ArchiveBillingEntityJob] Unknown entity type: {$this->type}");
            }

        } catch (\Throwable $e) {
            Log::error("[ArchiveBillingEntityJob] Failed to archive {$this->type} {$this->providerId} on {$this->provider}: " . $e->getMessage());
            
            // Re-throw to trigger retry mechanism if it's a transient issue
            throw $e;
        }
    }
}
