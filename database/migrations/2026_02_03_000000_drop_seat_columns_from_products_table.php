<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'seat_based')) {
                $table->dropColumn('seat_based');
            }

            if (Schema::hasColumn('products', 'max_seats')) {
                $table->dropColumn('max_seats');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'seat_based')) {
                $table->boolean('seat_based')->default(false);
            }

            if (! Schema::hasColumn('products', 'max_seats')) {
                $table->unsignedInteger('max_seats')->nullable();
            }
        });
    }
};
