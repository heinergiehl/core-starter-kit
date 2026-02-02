<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'invoice_pdf')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('invoice_pdf')->nullable()->after('hosted_invoice_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'invoice_pdf')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_pdf');
            });
        }
    }
};
