<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('planned');
            $table->string('category')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_public']);
        });

        Schema::create('feature_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['feature_request_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_votes');
        Schema::dropIfExists('feature_requests');
    }
};
