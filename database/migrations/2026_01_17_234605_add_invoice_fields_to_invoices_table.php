<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Add provider_invoice_id if it doesn't exist
            if (! Schema::hasColumn('invoices', 'provider_invoice_id')) {
                $table->string('provider_invoice_id')->nullable()->after('provider_id');
            }

            // Add invoice_number if it doesn't exist
            if (! Schema::hasColumn('invoices', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('provider_invoice_id');
            }

            // Add pdf_url if it doesn't exist
            if (! Schema::hasColumn('invoices', 'pdf_url')) {
                $table->text('pdf_url')->nullable()->after('hosted_invoice_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['provider_invoice_id', 'invoice_number', 'pdf_url']);
        });
    }
};
