<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->boolean('is_metered')->default(false)->after('allow_custom_amount');
            $table->string('usage_meter_name')->nullable()->after('is_metered');
            $table->string('usage_meter_key')->nullable()->after('usage_meter_name');
            $table->string('usage_unit_label')->nullable()->after('usage_meter_key');
            $table->unsignedBigInteger('usage_included_units')->nullable()->after('usage_unit_label');
            $table->unsignedInteger('usage_package_size')->nullable()->after('usage_included_units');
            $table->unsignedInteger('usage_overage_amount')->nullable()->after('usage_package_size');
            $table->string('usage_rounding_mode')->nullable()->after('usage_overage_amount');

            $table->index(['is_metered', 'is_active']);
            $table->index('usage_meter_key');
        });
    }

    public function down(): void
    {
        Schema::table('prices', function (Blueprint $table) {
            $table->dropIndex(['is_metered', 'is_active']);
            $table->dropIndex(['usage_meter_key']);
            $table->dropColumn([
                'is_metered',
                'usage_meter_name',
                'usage_meter_key',
                'usage_unit_label',
                'usage_included_units',
                'usage_package_size',
                'usage_overage_amount',
                'usage_rounding_mode',
            ]);
        });
    }
};
