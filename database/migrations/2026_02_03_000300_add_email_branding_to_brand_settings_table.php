<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_settings', function (Blueprint $table): void {
            $table->string('email_primary_color', 20)->nullable()->after('template');
            $table->string('email_secondary_color', 20)->nullable()->after('email_primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('brand_settings', function (Blueprint $table): void {
            $table->dropColumn(['email_primary_color', 'email_secondary_color']);
        });
    }
};
