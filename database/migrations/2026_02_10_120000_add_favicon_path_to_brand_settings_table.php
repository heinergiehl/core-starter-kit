<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('brand_settings', 'favicon_path')) {
            Schema::table('brand_settings', function (Blueprint $table): void {
                $table->string('favicon_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('brand_settings', 'favicon_path')) {
            Schema::table('brand_settings', function (Blueprint $table): void {
                $table->dropColumn('favicon_path');
            });
        }
    }
};
