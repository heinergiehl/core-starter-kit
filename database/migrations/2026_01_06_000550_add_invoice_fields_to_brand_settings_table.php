<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_settings', function (Blueprint $table) {
            $table->string('invoice_name')->nullable()->after('color_fg');
            $table->string('invoice_email')->nullable()->after('invoice_name');
            $table->text('invoice_address')->nullable()->after('invoice_email');
            $table->string('invoice_tax_id')->nullable()->after('invoice_address');
            $table->string('invoice_footer')->nullable()->after('invoice_tax_id');
        });
    }

    public function down(): void
    {
        Schema::table('brand_settings', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_name',
                'invoice_email',
                'invoice_address',
                'invoice_tax_id',
                'invoice_footer',
            ]);
        });
    }
};
