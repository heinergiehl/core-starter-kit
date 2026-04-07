<?php

namespace Tests\Feature\Livewire;

use App\Domain\Billing\Models\Subscription;
use App\Livewire\Profile\DeleteAccount;
use App\Livewire\Profile\UpdatePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileComponentTest extends TestCase
{
    use RefreshDatabase;

    private function createSubscribedUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        Subscription::factory()->active()->create(['user_id' => $user->id]);

        return $user;
    }

    public function test_update_password_validates_current_password(): void
    {
        $user = $this->createSubscribedUser([
            'password' => Hash::make('old-password'),
        ]);

        Livewire::actingAs($user)
            ->test(UpdatePassword::class)
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('updatePassword')
            ->assertHasErrors('current_password');
    }

    public function test_update_password_requires_confirmation(): void
    {
        $user = $this->createSubscribedUser([
            'password' => Hash::make('old-password'),
        ]);

        Livewire::actingAs($user)
            ->test(UpdatePassword::class)
            ->set('current_password', 'old-password')
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'different-password')
            ->call('updatePassword')
            ->assertHasErrors('password');
    }

    public function test_update_password_succeeds_with_valid_input(): void
    {
        $user = $this->createSubscribedUser([
            'password' => Hash::make('old-password'),
        ]);

        Livewire::actingAs($user)
            ->test(UpdatePassword::class)
            ->set('current_password', 'old-password')
            ->set('password', 'new-secure-password-123')
            ->set('password_confirmation', 'new-secure-password-123')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertDispatched('password-updated');

        $this->assertTrue(Hash::check('new-secure-password-123', $user->fresh()->password));
    }

    public function test_delete_account_requires_password(): void
    {
        $user = $this->createSubscribedUser([
            'password' => Hash::make('my-password'),
        ]);

        Livewire::actingAs($user)
            ->test(DeleteAccount::class)
            ->set('password', '')
            ->call('deleteAccount')
            ->assertHasErrors('password');
    }

    public function test_delete_account_rejects_wrong_password(): void
    {
        $user = $this->createSubscribedUser([
            'password' => Hash::make('my-password'),
        ]);

        Livewire::actingAs($user)
            ->test(DeleteAccount::class)
            ->set('password', 'wrong-password')
            ->call('deleteAccount')
            ->assertHasErrors('password');

        $this->assertNotNull($user->fresh());
    }

    public function test_delete_account_succeeds_with_correct_password(): void
    {
        $user = $this->createSubscribedUser([
            'password' => Hash::make('my-password'),
        ]);
        $userId = $user->id;

        Livewire::actingAs($user)
            ->test(DeleteAccount::class)
            ->set('password', 'my-password')
            ->call('deleteAccount')
            ->assertHasNoErrors();

        $this->assertNull(User::find($userId));
    }

    public function test_modal_toggle_works(): void
    {
        $user = $this->createSubscribedUser();

        Livewire::actingAs($user)
            ->test(DeleteAccount::class)
            ->assertSet('showModal', false)
            ->call('openModal')
            ->assertSet('showModal', true)
            ->call('closeModal')
            ->assertSet('showModal', false);
    }
}
