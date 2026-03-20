<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ActivityLogService
{
    public function log(
        string $category,
        string $event,
        ?Model $subject = null,
        ?User $actor = null,
        ?string $description = null,
        array $metadata = []
    ): ?ActivityLog {
        $request = $this->request();
        $resolvedActor = $actor ?? $this->authenticatedActor();

        $attributes = [
            'category' => $category,
            'event' => $event,
            'description' => $description,
            'actor_id' => $resolvedActor?->id,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ];

        if ($subject) {
            $attributes['subject_type'] = $subject->getMorphClass();
            $attributes['subject_id'] = $subject->getKey();
        }

        try {
            return ActivityLog::query()->create($attributes);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function authenticatedActor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function request(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        return $request instanceof Request ? $request : null;
    }
}
