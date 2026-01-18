<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            
            // Product information
            $table->string('product_name');
            $table->text('description')->nullable();
            
            // Pricing
            $table->integer('quantity')->default(1);
            $table->integer('unit_price')->comment('Price per unit in cents');
            $table->integer('total_amount')->comment('Total for this line item in cents');
            $table->decimal('tax_rate', 5, 2)->nullable()->comment('Tax rate for this item');
            
            // Billing period (for subscriptions)
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            
            // Raw data from provider
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Index for faster queries
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');
    }
};
