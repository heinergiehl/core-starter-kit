<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider');
            $table->string('plan_key');
            $table->string('price_key');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status')->default('pending');
            $table->string('currency', 3)->nullable();
            $table->unsignedInteger('amount')->nullable();
            $table->boolean('amount_is_minor')->default(true);
            $table->string('email')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('discount_id')->nullable()->constrained('discounts')->nullOnDelete();
            $table->string('discount_code')->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->string('provider_subscription_id')->nullable();
            $table->string('provider_customer_id')->nullable();
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('claim_sent_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_transaction_id']);
            $table->index(['provider', 'provider_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_intents');
    }
};
