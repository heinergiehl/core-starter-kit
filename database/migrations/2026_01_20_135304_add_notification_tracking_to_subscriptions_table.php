<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('welcome_email_sent_at')->nullable()->after('canceled_at');
            $table->timestamp('cancellation_email_sent_at')->nullable()->after('welcome_email_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['welcome_email_sent_at', 'cancellation_email_sent_at']);
        });
    }
};
