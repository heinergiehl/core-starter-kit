<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create the new mapping table
        Schema::create('price_provider_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_id')->constrained('prices')->cascadeOnDelete();
            $table->string('provider'); // 'stripe', 'paddle', 'lemonsqueezy'
            $table->string('provider_id');
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->unique(['price_id', 'provider']);
        });

        // 2. Migrate existing data
        // We need to iterate over existing prices that have provider info
        // and create mapping entries.
        $prices = DB::table('prices')
            ->whereNotNull('provider')
            ->whereNotNull('provider_id')
            ->get();

        foreach ($prices as $price) {
            DB::table('price_provider_mappings')->insert([
                'price_id' => $price->id,
                'provider' => $price->provider,
                'provider_id' => $price->provider_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Drop columns from prices table
        Schema::table('prices', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_id']); // Drop the old unique constraint if it exists
            $table->dropColumn(['provider', 'provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Add columns back
        Schema::table('prices', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('product_id');
            $table->string('provider_id')->nullable()->after('provider');
            $table->unique(['provider', 'provider_id']);
        });

        // 2. Restore data from mappings
        // Note: This is imperfect if a price has multiple mappings.
        // We will just take the first one found.
        $mappings = DB::table('price_provider_mappings')->get();

        foreach ($mappings as $mapping) {
            DB::table('prices')
                ->where('id', $mapping->price_id)
                ->update([
                    'provider' => $mapping->provider,
                    'provider_id' => $mapping->provider_id,
                ]);
        }

        // 3. Drop mapping table
        Schema::dropIfExists('price_provider_mappings');
    }
};
