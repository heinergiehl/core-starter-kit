<?php

namespace App\Jobs;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Organization\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSeatQuantityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $teamId)
    {
    }

    public function handle(BillingProviderManager $manager, EntitlementService $entitlementService): void
    {
        $team = Team::find($this->teamId);

        if (!$team) {
            return;
        }

        $subscription = Subscription::query()
            ->where('team_id', $team->id)
            ->whereIn('status', ['active', 'trialing'])
            ->latest('id')
            ->first();

        if (!$subscription) {
            return;
        }

        $quantity = $entitlementService->forTeam($team)->get('seats_in_use', 0);

        $manager->adapter($subscription->provider)->syncSeatQuantity($team, $quantity);
    }
}
