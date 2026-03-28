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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('personal_for_user_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        $timestamp = now();

        DB::table('users')
            ->select(['id', 'name', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($timestamp): void {
                foreach ($users as $user) {
                    DB::table('accounts')->insert([
                        'name' => filled($user->name) ? "{$user->name} Personal" : 'Personal Account',
                        'personal_for_user_id' => $user->id,
                        'created_at' => $user->created_at ?? $timestamp,
                        'updated_at' => $user->updated_at ?? $timestamp,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
