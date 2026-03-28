<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('member');
            $table->timestamps();

            $table->unique(['account_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        $timestamp = now();

        DB::table('accounts')
            ->select(['id', 'personal_for_user_id', 'created_at', 'updated_at'])
            ->whereNotNull('personal_for_user_id')
            ->orderBy('id')
            ->chunkById(100, function ($accounts) use ($timestamp): void {
                foreach ($accounts as $account) {
                    DB::table('account_memberships')->insert([
                        'account_id' => $account->id,
                        'user_id' => $account->personal_for_user_id,
                        'role' => 'owner',
                        'created_at' => $account->created_at ?? $timestamp,
                        'updated_at' => $account->updated_at ?? $timestamp,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_memberships');
    }
};
