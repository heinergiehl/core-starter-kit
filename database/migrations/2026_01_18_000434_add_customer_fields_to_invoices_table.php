<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Customer details (from Paddle checkout)
            $table->string('customer_name')->nullable()->after('team_id');
            $table->string('customer_email')->nullable();
            $table->text('billing_address')->nullable(); // JSON: {line1, line2, city, postal_code, country}
            $table->string('customer_vat_number')->nullable();
            
            // Financial breakdown
            $table->integer('subtotal')->nullable()->after('amount_paid')->comment('Amount before tax, in cents');
            $table->integer('tax_amount')->nullable()->comment('Tax amount in cents');
            $table->decimal('tax_rate', 5, 2)->nullable()->comment('Tax rate as percentage, e.g., 19.00');
            
            // PDF caching
            $table->timestamp('pdf_url_expires_at')->nullable()->after('pdf_url');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'customer_email',
                'billing_address',
                'customer_vat_number',
                'subtotal',
                'tax_amount',
                'tax_rate',
                'pdf_url_expires_at',
            ]);
        });
    }
};
