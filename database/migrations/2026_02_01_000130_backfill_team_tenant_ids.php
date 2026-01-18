<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('teams') || !Schema::hasTable('tenants')) {
            return;
        }

        $baseDomain = config('saas.tenancy.base_domain');
        $baseDomain = is_string($baseDomain) ? strtolower(trim($baseDomain)) : null;

        $teams = DB::table('teams')->whereNull('tenant_id')->get();

        foreach ($teams as $team) {
            $tenantId = DB::table('tenants')->insertGetId([
                'created_at' => now(),
                'updated_at' => now(),
                'data' => json_encode(['team_id' => $team->id]),
            ]);

            DB::table('teams')
                ->where('id', $team->id)
                ->update(['tenant_id' => $tenantId]);

            $domains = [];

            if ($baseDomain && !empty($team->subdomain)) {
                $subdomain = strtolower(trim((string) $team->subdomain));

                if ($subdomain !== '') {
                    $domains[] = "{$subdomain}.{$baseDomain}";
                }
            }

            if (!empty($team->domain)) {
                $customDomain = strtolower(trim((string) $team->domain));

                if ($customDomain !== '') {
                    $domains[] = $customDomain;
                }
            }

            $domains = array_values(array_unique($domains));

            foreach ($domains as $domain) {
                DB::table('domains')->insert([
                    'domain' => $domain,
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No-op: avoid destructive deletes during rollback.
    }
};
