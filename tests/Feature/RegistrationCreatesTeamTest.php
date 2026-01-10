<?php

namespace Tests\Feature;

use App\Domain\Organization\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationCreatesTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_team_and_membership(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'team_name' => 'Test Workspace',
            'email' => 'user@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'user@example.com')->firstOrFail();
        $this->assertNotNull($user->current_team_id);

        $team = Team::find($user->current_team_id);
        $this->assertNotNull($team);
        $this->assertSame('Test Workspace', $team->name);

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }
}
