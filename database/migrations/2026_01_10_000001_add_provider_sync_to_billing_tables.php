<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('is_active');
            $table->string('provider_id')->nullable()->after('provider');
            $table->timestamp('synced_at')->nullable()->after('provider_id');

            $table->unique(['provider', 'provider_id']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('is_active');
            $table->string('provider_id')->nullable()->after('provider');
            $table->timestamp('synced_at')->nullable()->after('provider_id');

            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_id']);
            $table->dropColumn(['provider', 'provider_id', 'synced_at']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_id']);
            $table->dropColumn(['provider', 'provider_id', 'synced_at']);
        });
    }
};
