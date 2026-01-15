<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('teams')
            ->whereNull('subdomain')
            ->update(['subdomain' => DB::raw('slug')]);
    }

    public function down(): void
    {
        DB::table('teams')
            ->whereColumn('subdomain', 'slug')
            ->update(['subdomain' => null]);
    }
};
