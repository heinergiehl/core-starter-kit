<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->boolean('allow_custom_amount')->default(false)->after('amount');
            $table->unsignedInteger('custom_amount_minimum')->nullable()->after('allow_custom_amount');
            $table->unsignedInteger('custom_amount_maximum')->nullable()->after('custom_amount_minimum');
            $table->unsignedInteger('custom_amount_default')->nullable()->after('custom_amount_maximum');
            $table->json('suggested_amounts')->nullable()->after('custom_amount_default');
        });
    }

    public function down(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->dropColumn([
                'allow_custom_amount',
                'custom_amount_minimum',
                'custom_amount_maximum',
                'custom_amount_default',
                'suggested_amounts',
            ]);
        });
    }
};
