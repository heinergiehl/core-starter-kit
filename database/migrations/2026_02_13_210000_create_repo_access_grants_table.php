<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repo_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('github');
            $table->string('repository_owner')->default('');
            $table->string('repository_name')->default('');
            $table->string('github_username')->nullable();
            $table->string('status', 64)->default('queued');
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'provider', 'repository_owner', 'repository_name'],
                'repo_access_grants_user_provider_repository_unique'
            );
            $table->index(['status']);
            $table->index(['provider', 'repository_owner', 'repository_name'], 'repo_access_grants_repo_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repo_access_grants');
    }
};
