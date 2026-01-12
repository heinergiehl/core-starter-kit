<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration merges the Plan entity into Product, simplifying the billing model.
     */
    public function up(): void
    {
        // Step 1: Add Plan fields to products table
        Schema::table('products', function (Blueprint $table) {
            $table->string('summary', 255)->nullable()->after('description');
            $table->string('type', 32)->default('subscription')->after('summary'); // subscription | one_time
            $table->boolean('seat_based')->default(false)->after('type');
            $table->unsignedInteger('max_seats')->nullable()->after('seat_based');
            $table->boolean('is_featured')->default(false)->after('max_seats');
            $table->json('features')->nullable()->after('is_featured');
            $table->json('entitlements')->nullable()->after('features');
        });

        // Step 2: Add product_id to prices table (nullable initially for migration)
        Schema::table('prices', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('id');
        });

        // Step 3: Migrate data - copy plan fields to their products
        // For each plan, update its product with the plan's fields
        $plans = DB::table('plans')->get();
        foreach ($plans as $plan) {
            if ($plan->product_id) {
                DB::table('products')
                    ->where('id', $plan->product_id)
                    ->update([
                        'summary' => $plan->summary,
                        'type' => $plan->type,
                        'seat_based' => $plan->seat_based,
                        'max_seats' => $plan->max_seats,
                        'is_featured' => $plan->is_featured,
                        'features' => $plan->features,
                        'entitlements' => $plan->entitlements,
                    ]);

                // Update prices - set product_id based on plan's product_id
                DB::table('prices')
                    ->where('plan_id', $plan->id)
                    ->update(['product_id' => $plan->product_id]);
            }
        }

        // Step 4: For plans without products, create products from the plan data
        $orphanPlans = DB::table('plans')->whereNull('product_id')->get();
        foreach ($orphanPlans as $plan) {
            $productId = DB::table('products')->insertGetId([
                'key' => $plan->key,
                'name' => $plan->name,
                'description' => $plan->description,
                'summary' => $plan->summary,
                'type' => $plan->type ?? 'subscription',
                'seat_based' => $plan->seat_based ?? false,
                'max_seats' => $plan->max_seats,
                'is_featured' => $plan->is_featured ?? false,
                'features' => $plan->features,
                'entitlements' => $plan->entitlements,
                'is_active' => $plan->is_active ?? true,
                'provider' => $plan->provider,
                'provider_id' => $plan->provider_id,
                'synced_at' => $plan->synced_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update prices for this plan
            DB::table('prices')
                ->where('plan_id', $plan->id)
                ->update(['product_id' => $productId]);
        }

        // Step 5: Drop plan_id foreign key and column from prices
        Schema::table('prices', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn('plan_id');
            
            // Add foreign key constraint for product_id
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });

        // Step 6: Drop plans table
        Schema::dropIfExists('plans');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate plans table
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key', 64)->unique();
            $table->string('name', 255);
            $table->string('summary', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('type', 32)->default('subscription');
            $table->boolean('seat_based')->default(false);
            $table->unsignedInteger('max_seats')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->json('features')->nullable();
            $table->json('entitlements')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('provider', 32)->nullable();
            $table->string('provider_id', 191)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_id']);
        });

        // Restore plan_id on prices
        Schema::table('prices', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreignId('plan_id')->nullable()->after('id');
            $table->dropColumn('product_id');
        });

        // Remove Plan fields from products
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['summary', 'type', 'seat_based', 'max_seats', 'is_featured', 'features', 'entitlements']);
        });
    }
};
