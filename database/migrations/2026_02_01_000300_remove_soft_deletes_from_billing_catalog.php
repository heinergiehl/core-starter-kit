<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First, hard-delete any soft-deleted records (they can be re-synced from provider)
        DB::table('prices')->whereNotNull('deleted_at')->delete();
        DB::table('products')->whereNotNull('deleted_at')->delete();

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });

        Schema::table('prices', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('prices', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
