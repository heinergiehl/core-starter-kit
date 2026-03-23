<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_user_edit_page_and_manage_public_author_profile_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $author = User::factory()->create([
            'public_author_name' => 'ShipSolid Team',
            'public_author_title' => 'Editorial Team',
            'public_author_bio' => 'Editorial byline used on public blog posts.',
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(EditUser::class, ['record' => $author->getRouteKey()])
            ->assertSeeText('Public author profile')
            ->assertSeeText('Public byline')
            ->assertSeeText('Public title')
            ->assertSeeText('Public bio')
            ->assertSet('data.public_author_name', 'ShipSolid Team')
            ->assertSet('data.public_author_title', 'Editorial Team')
            ->assertSet('data.public_author_bio', 'Editorial byline used on public blog posts.');
    }
}
