<?php

namespace App\Http\Controllers;

use App\Http\Requests\Onboarding\OnboardingStepOneRequest;
use App\Http\Requests\Onboarding\OnboardingStepTwoRequest;
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
    public function updateStepOne(OnboardingStepOneRequest $request): RedirectResponse
    {
        $request->user()->update(['name' => $request->validated('name')]);

        return redirect()->route('onboarding.show', ['step' => 2]);
    }

    public function updateStepTwo(OnboardingStepTwoRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'locale' => \App\Enums\Locale::tryFrom($request->validated('locale')) ?? $user->locale,
            'onboarding_completed_at' => now(),
        ]);

        return redirect()->route('dashboard')
            ->with('success', __('Welcome aboard! Your account is all set up.'));
    }

    /**
     * @deprecated Use updateStepOne() and updateStepTwo() instead.
     */
    public function update(Request $request): RedirectResponse
    {
        $step = (int) $request->input('step', 1);

        return match ($step) {
            1 => $this->updateStepOne(app(OnboardingStepOneRequest::class)),
            2 => $this->updateStepTwo(app(OnboardingStepTwoRequest::class)),
            default => redirect()->route('onboarding.show'),
        };
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
