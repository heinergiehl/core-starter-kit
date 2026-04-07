<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_public')->default(false);
            $table->boolean('requires_auth')->default(false);
            $table->boolean('allow_multiple_submissions')->default(false);
            $table->string('submit_label')->default('Submit');
            $table->string('success_title')->nullable();
            $table->text('success_message')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('questions');
            $table->timestamps();

            $table->index(['status', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};
