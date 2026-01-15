<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_provider_mappings', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreignId('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::table('price_provider_mappings', function (Blueprint $table) {
            $table->dropForeign(['price_id']);
            $table->foreignId('price_id')->nullable()->change();
            $table->foreign('price_id')->references('id')->on('prices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('price_provider_mappings', function (Blueprint $table) {
            $table->dropForeign(['price_id']);
            $table->foreignId('price_id')->nullable(false)->change();
            $table->foreign('price_id')->references('id')->on('prices')->cascadeOnDelete();
        });

        Schema::table('product_provider_mappings', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreignId('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
