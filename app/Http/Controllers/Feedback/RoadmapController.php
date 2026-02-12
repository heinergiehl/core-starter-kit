<?php

namespace App\Http\Controllers\Feedback;

use App\Domain\Feedback\Models\FeatureRequest;
use App\Domain\Feedback\Models\FeatureVote;
use App\Enums\FeatureCategory;
use App\Enums\FeatureStatus;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class RoadmapController
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $query = FeatureRequest::query()
            ->where('is_public', true);

        if ($status) {
            $query->where('status', $status);
        }

        $requests = $query
            ->orderByDesc('votes_count')
            ->orderByDesc('created_at')
            ->paginate(20);

        $votedIds = [];

        if ($request->user()) {
            $votedIds = $request->user()
                ->featureVotes()
                ->pluck('feature_request_id')
                ->all();
        }

        return view('roadmap.index', [
            'requests' => $requests,
            'status' => $status,
            'votedIds' => $votedIds,
            'statuses' => [FeatureStatus::Planned->value, FeatureStatus::InProgress->value, FeatureStatus::Completed->value],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::enum(FeatureCategory::class)],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $idempotencyCacheKey = sprintf('roadmap:store:%d:%s', $user->id, $data['idempotency_key']);

        // Prevent duplicate creates when the same submission is retried.
        if (! Cache::add($idempotencyCacheKey, true, now()->addDay())) {
            return redirect()->route('roadmap')->with('status', 'Thanks for the feedback!');
        }

        try {
            $this->createFeatureRequestWithRetry($user->id, $data);
        } catch (Throwable $exception) {
            Cache::forget($idempotencyCacheKey);

            throw $exception;
        }

        return redirect()->route('roadmap')->with('status', 'Thanks for the feedback!');
    }

    public function vote(Request $request, FeatureRequest $feature): RedirectResponse
    {
        if (! $feature->is_public) {
            abort(404);
        }

        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        DB::transaction(function () use ($feature, $user): void {
            $lockedFeature = FeatureRequest::query()
                ->whereKey($feature->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedFeature || ! $lockedFeature->is_public) {
                abort(404);
            }

            $vote = FeatureVote::query()
                ->where('feature_request_id', $lockedFeature->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($vote) {
                $vote->delete();
            } else {
                FeatureVote::query()->create([
                    'feature_request_id' => $lockedFeature->id,
                    'user_id' => $user->id,
                ]);
            }

            $lockedFeature->update([
                'votes_count' => FeatureVote::query()
                    ->where('feature_request_id', $lockedFeature->id)
                    ->count(),
            ]);
        });

        return redirect()->route('roadmap', ['status' => $request->query('status')]);
    }

    /**
     * @param  array{title: string, description?: string|null, category: string}  $data
     */
    private function createFeatureRequestWithRetry(int $userId, array $data): void
    {
        $maxAttempts = 6;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                FeatureRequest::query()->create([
                    'user_id' => $userId,
                    'title' => $data['title'],
                    'slug' => FeatureRequest::slugCandidate($data['title'], $attempt),
                    'description' => $data['description'] ?? null,
                    'category' => $data['category'],
                    'status' => FeatureStatus::Pending,
                    'is_public' => false,
                ]);

                return;
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception) || $attempt === $maxAttempts - 1) {
                    throw $exception;
                }
            }
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();

        if (in_array($code, ['23000', '23505'], true)) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
