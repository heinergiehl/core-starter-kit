<?php

namespace Database\Factories\Domain\Identity\Models;

use App\Domain\Identity\Models\Account;
use App\Domain\Identity\Models\AccountMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Identity\Models\AccountMembership>
 */
class AccountMembershipFactory extends Factory
{
    protected $model = AccountMembership::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'role' => 'member',
        ];
    }
}
