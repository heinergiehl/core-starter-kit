<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'products',
            'prices',
            'subscriptions',
            'orders',
            'invoices',
            'billing_customers',
            'discounts',
            // 'plans' table was merged/dropped
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    if (!Schema::hasColumn($table->getTable(), 'tenant_id')) {
                        $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                        $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'products',
            'prices',
            'subscriptions',
            'orders',
            'invoices',
            'billing_customers',
            'discounts',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }
};
