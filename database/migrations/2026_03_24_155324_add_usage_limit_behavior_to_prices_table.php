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
        Schema::table('prices', function (Blueprint $table): void {
            $table->string('usage_limit_behavior', 32)
                ->default('bill_overage')
                ->after('usage_rounding_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prices', function (Blueprint $table): void {
            $table->dropColumn('usage_limit_behavior');
        });
    }
};
