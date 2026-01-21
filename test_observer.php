try {
    $user = \App\Models\User::first();
    $team = $user->currentTeam;
    $subId = 'test_' . time();
    
    echo "Creating subscription $subId for Team {$team->id}...\n";
    
    $sub = \App\Domain\Billing\Models\Subscription::create([
        'team_id' => $team->id,
        'provider' => 'paddle',
        'provider_id' => $subId,
        'plan_key' => 'monthly',
        'status' => 'trialing',
        'trial_ends_at' => now()->addDays(14),
    ]);
    
    echo "Subscription created. ID: {$sub->id}\n";
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
