<?php

namespace Tests\Feature;

use App\Domain\Feedback\Models\FeatureRequest;
use App\Domain\Feedback\Models\FeatureVote;
use App\Enums\FeatureCategory;
use App\Enums\FeatureStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoadmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_submit_lock_on_roadmap_forms(): void
    {
        $user = User::factory()->create();

        $feature = FeatureRequest::query()->create([
            'user_id' => $user->id,
            'title' => 'Roadmap submit lock',
            'slug' => 'roadmap-submit-lock',
            'status' => FeatureStatus::Planned,
            'is_public' => true,
        ]);

        $status = FeatureStatus::Planned->value;
        $response = $this->actingAs($user)->get(route('roadmap', ['status' => $status]));

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString(
            'action="'.route('roadmap.vote', ['feature' => $feature, 'status' => $status]).'" data-submit-lock',
            $html
        );
        $this->assertStringContainsString(
            'action="'.route('roadmap.store').'" class="mt-4 space-y-4" data-submit-lock',
            $html
        );
        $this->assertStringContainsString('name="idempotency_key"', $html);
    }

    public function test_duplicate_submit_with_same_idempotency_key_creates_only_one_request(): void
    {
        $user = User::factory()->create();
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'title' => 'Idempotent roadmap request',
            'description' => 'Should only be created once.',
            'category' => FeatureCategory::Feature->value,
            'idempotency_key' => $idempotencyKey,
        ];

        $this->actingAs($user)
            ->post(route('roadmap.store'), $payload)
            ->assertRedirect(route('roadmap'))
            ->assertSessionHas('status', 'Thanks for the feedback!');

        $this->actingAs($user)
            ->post(route('roadmap.store'), $payload)
            ->assertRedirect(route('roadmap'))
            ->assertSessionHas('status', 'Thanks for the feedback!');

        $this->assertSame(
            1,
            FeatureRequest::query()
                ->where('user_id', $user->id)
                ->where('title', 'Idempotent roadmap request')
                ->count()
        );
    }

    public function test_vote_toggle_recomputes_votes_count_without_underflow(): void
    {
        $user = User::factory()->create();

        $feature = FeatureRequest::query()->create([
            'user_id' => $user->id,
            'title' => 'Vote integrity',
            'slug' => 'vote-integrity',
            'status' => FeatureStatus::Planned,
            'is_public' => true,
            'votes_count' => 0,
        ]);

        FeatureVote::query()->create([
            'feature_request_id' => $feature->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('roadmap.vote', ['feature' => $feature]))
            ->assertRedirect(route('roadmap'));

        $this->assertDatabaseMissing('feature_votes', [
            'feature_request_id' => $feature->id,
            'user_id' => $user->id,
        ]);
        $this->assertSame(0, $feature->fresh()->votes_count);
    }
}
