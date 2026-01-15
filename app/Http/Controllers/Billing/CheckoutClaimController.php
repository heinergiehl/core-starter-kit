<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Billing\Adapters\PaddleAdapter;
use App\Domain\Billing\Models\CheckoutIntent;
use App\Domain\Organization\Models\Team;
use App\Domain\Organization\Services\TeamProvisioner;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutClaimController
{
    public function __invoke(Request $request, CheckoutIntent $intent, TeamProvisioner $teams): View|RedirectResponse
    {
        if (strtolower($intent->provider) !== 'paddle') {
            return view('billing.claim', [
                'status' => 'invalid',
            ]);
        }

        if (!in_array($intent->status, ['paid', 'completed'], true)) {
            return view('billing.claim', [
                'status' => 'pending',
            ]);
        }

        if ($intent->claimed_at) {
            return view('billing.claim', [
                'status' => 'claimed',
            ]);
        }

        if (!$intent->email) {
            return view('billing.claim', [
                'status' => 'missing_email',
            ]);
        }

        $user = null;
        $team = null;

        try {
            DB::transaction(function () use (&$user, &$team, $intent, $teams): void {
                $lockedIntent = CheckoutIntent::query()
                    ->whereKey($intent->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedIntent || $lockedIntent->claimed_at) {
                    return;
                }

                $user = User::query()->firstOrCreate(
                    ['email' => $lockedIntent->email],
                    [
                        'name' => $this->nameFromEmail($lockedIntent->email),
                        'password' => Str::random(32),
                    ]
                );

                if (!$user->email_verified_at) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                $team = null;
                if ($lockedIntent->team_id) {
                    $team = Team::find($lockedIntent->team_id);
                }

                if (!$team) {
                    $team = $teams->createDefaultTeam($user);
                    $lockedIntent->team_id = $team->id;
                }

                $lockedIntent->user_id = $user->id;

                app(PaddleAdapter::class)->finalizeCheckoutIntent($lockedIntent, $team, $user);

                $lockedIntent->status = 'claimed';
                $lockedIntent->claimed_at = now();
                $lockedIntent->save();
            });
        } catch (\Throwable $exception) {
            report($exception);

            return view('billing.claim', [
                'status' => 'error',
            ]);
        }

        if (!$user) {
            return view('billing.claim', [
                'status' => 'claimed',
            ]);
        }

        Auth::login($user, true);

        return redirect()
            ->route('billing.index')
            ->with('success', __('Your subscription is now active.'));
    }

    private function nameFromEmail(string $email): string
    {
        $local = trim(strstr($email, '@', true) ?: $email);
        $name = str_replace(['.', '_', '-'], ' ', $local);
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        return $name !== '' ? ucwords($name) : 'Customer';
    }
}
