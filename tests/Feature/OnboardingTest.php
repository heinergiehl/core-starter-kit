<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_sees_onboarding(): void
    {
        config()->set('saas.features.onboarding', true);

        $user = User::factory()->create([
            'onboarding_completed_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/onboarding');

        $response->assertOk();
        $response->assertSee('Welcome aboard');
    }

    public function test_completed_user_redirects_from_onboarding(): void
    {
        config()->set('saas.features.onboarding', true);

        $user = User::factory()->create([
            'onboarding_completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/onboarding');

        $response->assertRedirect('/dashboard');
    }

    public function test_onboarding_step_1_updates_name(): void
    {
        config()->set('saas.features.onboarding', true);

        $user = User::factory()->create([
            'name' => 'Old Name',
            'onboarding_completed_at' => null,
        ]);

        $response = $this->actingAs($user)->post('/onboarding', [
            'step' => 1,
            'name' => 'New Name',
        ]);

        $response->assertRedirect('/onboarding?step=2');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    }

    public function test_onboarding_step_2_completes_onboarding(): void
    {
        config()->set('saas.features.onboarding', true);

        $user = User::factory()->create([
            'onboarding_completed_at' => null,
        ]);

        $response = $this->actingAs($user)->post('/onboarding', [
            'step' => 2,
            'locale' => 'de',
        ]);

        $response->assertRedirect('/dashboard');
        $user->refresh();
        $this->assertNotNull($user->onboarding_completed_at);
        $this->assertEquals(\App\Enums\Locale::German, $user->locale);
    }

    public function test_onboarding_can_be_skipped(): void
    {
        config()->set('saas.features.onboarding', true);

        $user = User::factory()->create([
            'onboarding_completed_at' => null,
        ]);

        $response = $this->actingAs($user)->post('/onboarding/skip');

        $response->assertRedirect('/dashboard');
        $user->refresh();
        $this->assertNotNull($user->onboarding_completed_at);
    }
}
