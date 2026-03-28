<?php

namespace Tests\Unit\Domain\Identity;

use App\Domain\Identity\Contracts\CurrentAccountResolver;
use App\Domain\Identity\Models\Account;
use App\Domain\Identity\Models\AccountMembership;
use App\Domain\Identity\Services\SessionCurrentAccountResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SessionCurrentAccountResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_the_personal_account_by_default(): void
    {
        $user = User::factory()->create();
        $this->startSession();

        $this->actingAs($user);

        $account = app(CurrentAccountResolver::class)->forUser($user);

        $this->assertNotNull($account);
        $this->assertSame($user->personalAccount?->id, $account?->id);
        $this->assertSame($account?->id, session(SessionCurrentAccountResolver::SESSION_KEY));
    }

    #[Test]
    public function it_uses_a_session_selected_account_when_the_user_is_a_member(): void
    {
        $user = User::factory()->create();
        $sharedAccount = Account::factory()->create();
        $this->startSession();

        AccountMembership::factory()->create([
            'account_id' => $sharedAccount->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $this->actingAs($user);
        session()->put(SessionCurrentAccountResolver::SESSION_KEY, $sharedAccount->id);

        $account = app(CurrentAccountResolver::class)->forUser($user);

        $this->assertNotNull($account);
        $this->assertSame($sharedAccount->id, $account?->id);
    }

    #[Test]
    public function it_falls_back_to_the_personal_account_when_the_session_points_to_an_unavailable_account(): void
    {
        $user = User::factory()->create();
        $otherAccount = Account::factory()->create();
        $this->startSession();

        $this->actingAs($user);
        session()->put(SessionCurrentAccountResolver::SESSION_KEY, $otherAccount->id);

        $account = app(CurrentAccountResolver::class)->forUser($user);

        $this->assertNotNull($account);
        $this->assertSame($user->personalAccount?->id, $account?->id);
        $this->assertSame($user->personalAccount?->id, session(SessionCurrentAccountResolver::SESSION_KEY));
    }

    #[Test]
    public function it_can_store_the_selected_account_for_a_valid_member(): void
    {
        $user = User::factory()->create();
        $sharedAccount = Account::factory()->create();
        $this->startSession();

        AccountMembership::factory()->create([
            'account_id' => $sharedAccount->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $this->actingAs($user);

        app(CurrentAccountResolver::class)->setForUser($user, $sharedAccount);

        $this->assertSame($sharedAccount->id, session(SessionCurrentAccountResolver::SESSION_KEY));
        $this->assertSame($sharedAccount->id, app(CurrentAccountResolver::class)->idForUser($user));
    }

    #[Test]
    public function it_rejects_setting_an_account_the_user_does_not_belong_to(): void
    {
        $user = User::factory()->create();
        $otherAccount = Account::factory()->create();
        $this->startSession();

        $this->actingAs($user);

        $this->expectException(InvalidArgumentException::class);

        app(CurrentAccountResolver::class)->setForUser($user, $otherAccount);
    }
}
