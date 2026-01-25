<?php

namespace App\Domain\Identity\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_id';

    public const IMPERSONATED_KEY = 'impersonated_user_id';

    /**
     * Start impersonating a user.
     */
    public function impersonate(User $impersonator, User $target): bool
    {
        // Check if impersonator has permission
        if (! $this->canImpersonate($impersonator, $target)) {
            return false;
        }

        // Cannot impersonate yourself
        if ($impersonator->id === $target->id) {
            return false;
        }

        // Cannot impersonate another admin (optional safety)
        if ($target->is_admin) {
            return false;
        }

        // Store the original user's ID
        Session::put(self::SESSION_KEY, $impersonator->id);
        Session::put(self::IMPERSONATED_KEY, $target->id);

        // Log out current user and log in as target
        Auth::login($target);

        // Log this action for audit
        Log::info('User impersonation started', [
            'impersonator_id' => $impersonator->id,
            'impersonator_email' => $impersonator->email,
            'impersonated_id' => $target->id,
        ]);

        return true;
    }

    /**
     * Stop impersonating and return to original user.
     */
    public function stopImpersonating(): bool
    {
        $impersonatorId = Session::get(self::SESSION_KEY);

        if (! $impersonatorId) {
            return false;
        }

        $impersonator = User::find($impersonatorId);

        if (! $impersonator) {
            $this->clearSession();

            return false;
        }

        // Log the stop action
        $impersonatedId = Session::get(self::IMPERSONATED_KEY);
        $impersonated = User::find($impersonatedId);

        if ($impersonated) {
            Log::info('User impersonation ended', [
                'impersonator_id' => $impersonator->id,
                'impersonator_email' => $impersonator->email,
                'impersonated_id' => $impersonated->id,
            ]);
        }

        // Clear session and log back in as original user
        $this->clearSession();
        Auth::login($impersonator);

        return true;
    }

    /**
     * Check if currently impersonating.
     */
    public function isImpersonating(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    /**
     * Get the original impersonator user.
     */
    public function getImpersonator(): ?User
    {
        $id = Session::get(self::SESSION_KEY);

        return $id ? User::find($id) : null;
    }

    /**
     * Check if a user can impersonate others.
     */
    public function canImpersonate(User $user, ?User $target = null): bool
    {
        // Use the Gate defined in AuthServiceProvider (or Policy)
        // This allows for flexible configuration (e.g. support staff)
        if ($target) {
            return \Illuminate\Support\Facades\Gate::forUser($user)->allows('impersonate', $target);
        }

        // General check if they have permission to try impersonating
        return \Illuminate\Support\Facades\Gate::forUser($user)->allows('impersonate');
    }

    /**
     * Clear impersonation session data.
     */
    protected function clearSession(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget(self::IMPERSONATED_KEY);
    }
}
