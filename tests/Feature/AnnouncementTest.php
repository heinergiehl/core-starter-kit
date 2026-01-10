<?php

namespace Tests\Feature;

use App\Domain\Content\Models\Announcement;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithTeam(): User
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => now(),
        ]);

        $team = Team::factory()->create([
            'owner_id' => $user->id,
        ]);

        // Set current team
        $user->update(['current_team_id' => $team->id]);

        return $user->fresh();
    }

    public function test_active_announcement_is_displayed_on_dashboard(): void
    {
        $user = $this->createUserWithTeam();

        Announcement::factory()->create([
            'title' => 'Test Dashboard Announcement XYZ123',
            'message' => 'This is a test message',
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Test Dashboard Announcement XYZ123');
    }

    public function test_inactive_announcement_is_not_displayed(): void
    {
        $user = $this->createUserWithTeam();

        Announcement::factory()->create([
            'title' => 'HIDDEN_UNIQUE_ANNOUNCEMENT_12345',
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('HIDDEN_UNIQUE_ANNOUNCEMENT_12345');
    }

    public function test_scheduled_announcement_respects_dates(): void
    {
        $user = $this->createUserWithTeam();

        // Announcement that hasn't started yet
        Announcement::factory()->create([
            'title' => 'FUTURE_UNIQUE_ANNOUNCEMENT_99999',
            'is_active' => true,
            'starts_at' => now()->addDay(),
        ]);

        // Announcement that has ended
        Announcement::factory()->create([
            'title' => 'PAST_UNIQUE_ANNOUNCEMENT_88888',
            'is_active' => true,
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('FUTURE_UNIQUE_ANNOUNCEMENT_99999');
        $response->assertDontSee('PAST_UNIQUE_ANNOUNCEMENT_88888');
    }

    public function test_announcement_can_be_dismissed(): void
    {
        $announcement = Announcement::factory()->create([
            'title' => 'Dismissible',
            'is_active' => true,
            'is_dismissible' => true,
        ]);

        // Dismiss the announcement
        $response = $this->post("/announcements/{$announcement->id}/dismiss");

        $response->assertOk();

        // Verify it's stored in session
        $this->assertEquals([$announcement->id], session('dismissed_announcements'));
    }

    public function test_announcement_model_active_scope(): void
    {
        // Active announcement
        $active = Announcement::factory()->create([
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        // Inactive announcement
        Announcement::factory()->create([
            'is_active' => false,
        ]);

        // Future announcement
        Announcement::factory()->create([
            'is_active' => true,
            'starts_at' => now()->addDay(),
        ]);

        // Expired announcement
        Announcement::factory()->create([
            'is_active' => true,
            'ends_at' => now()->subDay(),
        ]);

        $activeAnnouncements = Announcement::active()->get();

        $this->assertCount(1, $activeAnnouncements);
        $this->assertEquals($active->id, $activeAnnouncements->first()->id);
    }
}
