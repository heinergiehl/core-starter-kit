<?php

namespace App\Http\Controllers\Feedback;

use App\Domain\Feedback\Models\FeatureRequest;
use App\Domain\Feedback\Models\FeatureVote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

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
            'statuses' => ['planned', 'in_progress', 'complete'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:80'],
        ]);

        $user = $request->user();
        $teamId = $user?->current_team_id;

        $baseSlug = Str::slug($data['title']) ?: Str::random(8);
        $slug = $baseSlug;
        $counter = 1;

        while (FeatureRequest::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        FeatureRequest::query()->create([
            'user_id' => $user?->id,
            'team_id' => $teamId,
            'title' => $data['title'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'status' => 'planned',
            'is_public' => true,
        ]);

        return redirect()->route('roadmap')->with('status', 'Thanks for the feedback!');
    }

    public function vote(Request $request, FeatureRequest $feature): RedirectResponse
    {
        if (!$feature->is_public) {
            abort(404);
        }

        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        DB::transaction(function () use ($feature, $user): void {
            $vote = FeatureVote::query()
                ->where('feature_request_id', $feature->id)
                ->where('user_id', $user->id)
                ->first();

            if ($vote) {
                $vote->delete();
                $feature->decrement('votes_count');
                return;
            }

            FeatureVote::query()->create([
                'feature_request_id' => $feature->id,
                'user_id' => $user->id,
            ]);

            $feature->increment('votes_count');
        });

        return redirect()->route('roadmap', ['status' => $request->query('status')]);
    }
}
