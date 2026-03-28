<?php

namespace Tests\Unit\Models;

use App\Domain\Billing\Contracts\BillingOwnerResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserAccountTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_personal_account_and_owner_membership_for_new_users(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('accounts', [
            'personal_for_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('account_memberships', [
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertNotNull($user->fresh()->currentAccount());
    }

    #[Test]
    public function it_resolves_the_current_billing_owner_from_the_users_personal_account(): void
    {
        $user = User::factory()->create();

        $owner = app(BillingOwnerResolver::class)->forUser($user);

        $this->assertNotNull($owner);
        $this->assertTrue($owner->isAccount());
        $this->assertSame($user->id, $owner->billingUserId());
        $this->assertSame($user->currentAccount()?->id, $owner->id);
    }
}
