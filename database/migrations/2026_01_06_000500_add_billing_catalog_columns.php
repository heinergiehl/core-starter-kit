<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('summary')->nullable()->after('name');
            $table->string('type')->default('subscription')->after('description');
            $table->boolean('seat_based')->default(false)->after('type');
            $table->unsignedInteger('max_seats')->nullable()->after('seat_based');
            $table->boolean('is_featured')->default(false)->after('is_active');
            $table->json('features')->nullable()->after('is_featured');
            $table->json('entitlements')->nullable()->after('features');
        });

        Schema::table('prices', function (Blueprint $table) {
            $table->string('key')->nullable()->after('plan_id');
            $table->string('label')->nullable()->after('provider_id');
            $table->unsignedInteger('interval_count')->default(1)->after('interval');
            $table->boolean('has_trial')->default(false)->after('type');
            $table->string('trial_interval')->nullable()->after('has_trial');
            $table->unsignedInteger('trial_interval_count')->nullable()->after('trial_interval');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'summary',
                'type',
                'seat_based',
                'max_seats',
                'is_featured',
                'features',
                'entitlements',
            ]);
        });

        Schema::table('prices', function (Blueprint $table) {
            $table->dropColumn([
                'key',
                'label',
                'interval_count',
                'has_trial',
                'trial_interval',
                'trial_interval_count',
            ]);
        });
    }
};
