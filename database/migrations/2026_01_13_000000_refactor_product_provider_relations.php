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
        Schema::create('product_provider_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('provider'); // stripe, paddle, lemonsqueezy
            $table->string('provider_id'); // prod_123, pro_456
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->unique(['product_id', 'provider']); // One ID per provider per product
        });

        // 2. Migrate existing data
        $products = DB::table('products')->whereNotNull('provider')->whereNotNull('provider_id')->get();
        
        foreach ($products as $product) {
            DB::table('product_provider_mappings')->insert([
                'product_id' => $product->id,
                'provider' => $product->provider,
                'provider_id' => $product->provider_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Drop columns from products table
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_id']);
            $table->dropColumn(['provider', 'provider_id', 'synced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Restore columns
        Schema::table('products', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('is_active');
            $table->string('provider_id')->nullable()->after('provider');
            $table->timestamp('synced_at')->nullable()->after('provider_id');
            
            $table->unique(['provider', 'provider_id']);
        });

        // 2. Restore data (best effort, restore first mapping found)
        $mappings = DB::table('product_provider_mappings')->get();
        
        foreach ($mappings as $mapping) {
            // Only update if not already set (since we can only store one provider back on the main table)
            // This is a lossy reverse migration if multiple providers exist, but acceptable for rollback of this specific change
            DB::table('products')
                ->where('id', $mapping->product_id)
                ->whereNull('provider_id') // Avoid overwriting if multiple exist
                ->update([
                    'provider' => $mapping->provider,
                    'provider_id' => $mapping->provider_id,
                ]);
        }

        // 3. Drop mapping table
        Schema::dropIfExists('product_provider_mappings');
    }
};
