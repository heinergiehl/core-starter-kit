<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_customers', function (Blueprint $table) {
            $table->foreignId('account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('accounts')
                ->nullOnDelete();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('accounts')
                ->nullOnDelete();
            $table->index(['account_id', 'status'], 'subscriptions_account_id_status_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('accounts')
                ->nullOnDelete();
            $table->index(['account_id', 'status'], 'orders_account_id_status_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('accounts')
                ->nullOnDelete();
            $table->index(['account_id', 'status'], 'invoices_account_id_status_index');
        });

        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->foreignId('account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('accounts')
                ->nullOnDelete();
            $table->index(['account_id', 'status'], 'checkout_sessions_account_id_status_index');
        });

        $this->backfillAccountOwnership();

        Schema::table('billing_customers', function (Blueprint $table) {
            $table->dropUnique('billing_customers_user_id_provider_unique');
            $table->unique(['account_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::table('billing_customers', function (Blueprint $table) {
            $table->dropUnique('billing_customers_account_id_provider_unique');
            $table->unique(['user_id', 'provider']);
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_account_id_status_index');
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_account_id_status_index');
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_account_id_status_index');
            $table->dropConstrainedForeignId('account_id');
        });

        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropIndex('checkout_sessions_account_id_status_index');
            $table->dropConstrainedForeignId('account_id');
        });
    }

    private function backfillAccountOwnership(): void
    {
        $accountIdsByUserId = DB::table('accounts')
            ->whereNotNull('personal_for_user_id')
            ->pluck('id', 'personal_for_user_id');

        foreach ([
            'billing_customers',
            'subscriptions',
            'orders',
            'invoices',
            'checkout_sessions',
        ] as $table) {
            $this->backfillTableAccountIds($table, $accountIdsByUserId);
        }
    }

    private function backfillTableAccountIds(string $table, Collection $accountIdsByUserId): void
    {
        DB::table($table)
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $rows) use ($table, $accountIdsByUserId): void {
                foreach ($rows as $row) {
                    $accountId = $accountIdsByUserId->get((string) $row->user_id)
                        ?? $accountIdsByUserId->get((int) $row->user_id);

                    if (! $accountId) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['account_id' => $accountId]);
                }
            });
    }
};
