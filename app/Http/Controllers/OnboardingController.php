<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * User Onboarding controller.
 *
 * Handles the multi-step onboarding wizard for new users.
 */
class OnboardingController extends Controller
{
    /**
     * Show the current onboarding step.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_completed_at) {
            return redirect()->route('dashboard');
        }

        $step = $request->query('step', 1);

        return view('onboarding.show', [
            'user' => $user,
            'step' => (int) $step,
            'totalSteps' => 2,
        ]);
    }

    /**
     * Process the current step and advance.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $step = (int) $request->input('step', 1);

        switch ($step) {
            case 1:
                // Welcome step - just name confirmation
                $request->validate([
                    'name' => ['required', 'string', 'max:255'],
                ]);
                $user->update(['name' => $request->name]);

                return redirect()->route('onboarding.show', ['step' => 2]);

            case 2:
                // Preferences and complete
                $request->validate([
                    'locale' => ['nullable', 'string', 'max:10'],
                ]);

                $user->update([
                    'locale' => \App\Enums\Locale::tryFrom($request->locale) ?? $user->locale,
                    'onboarding_completed_at' => now(),
                ]);

                return redirect()->route('dashboard')
                    ->with('success', __('Welcome aboard! Your account is all set up.'));
        }

        return redirect()->route('onboarding.show');
    }

    /**
     * Skip onboarding entirely.
     */
    public function skip(Request $request): RedirectResponse
    {
        $request->user()->update(['onboarding_completed_at' => now()]);

        return redirect()->route('dashboard');
    }
}
