<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 1800;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public bool $includeDeleted = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('[SyncProductsJob] Starting sync...');

        try {
            $exitCode = Artisan::call('billing:sync-products', [
                '--force' => $this->includeDeleted,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException("Artisan command failed with exit code {$exitCode}");
            }

            Log::info('[SyncProductsJob] Sync completed successfully.');
        } catch (Throwable $e) {
            Log::error('[SyncProductsJob] Sync failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
