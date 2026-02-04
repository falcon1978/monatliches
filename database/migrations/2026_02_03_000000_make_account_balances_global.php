<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $accountIds = DB::table('account_balances')
                ->select('account_id')
                ->groupBy('account_id')
                ->pluck('account_id');

            foreach ($accountIds as $accountId) {
                $keepId = DB::table('account_balances')
                    ->where('account_id', $accountId)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->value('id');

                if ($keepId) {
                    DB::table('account_balances')
                        ->where('account_id', $accountId)
                        ->where('id', '!=', $keepId)
                        ->delete();
                }
            }
        });

        $indexExists = function (string $indexName): bool {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'account_balances')
                ->where('index_name', $indexName)
                ->exists();
        };

        if (! $indexExists('account_balances_user_id_account_id_index')) {
            DB::statement('CREATE INDEX account_balances_user_id_account_id_index ON account_balances (user_id, account_id)');
        }

        if (! $indexExists('account_balances_month_id_index')) {
            DB::statement('CREATE INDEX account_balances_month_id_index ON account_balances (month_id)');
        }

        if (! $indexExists('account_balances_account_id_unique')) {
            DB::statement('ALTER TABLE account_balances ADD UNIQUE account_balances_account_id_unique (account_id)');
        }

        if ($indexExists('account_balances_month_id_account_id_unique')) {
            DB::statement('ALTER TABLE account_balances DROP INDEX account_balances_month_id_account_id_unique');
        }

        if ($indexExists('account_balances_user_id_month_id_index')) {
            DB::statement('ALTER TABLE account_balances DROP INDEX account_balances_user_id_month_id_index');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexExists = function (string $indexName): bool {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'account_balances')
                ->where('index_name', $indexName)
                ->exists();
        };

        if ($indexExists('account_balances_account_id_unique')) {
            DB::statement('ALTER TABLE account_balances DROP INDEX account_balances_account_id_unique');
        }

        if ($indexExists('account_balances_user_id_account_id_index')) {
            DB::statement('ALTER TABLE account_balances DROP INDEX account_balances_user_id_account_id_index');
        }

        if (! $indexExists('account_balances_month_id_account_id_unique')) {
            DB::statement('ALTER TABLE account_balances ADD UNIQUE account_balances_month_id_account_id_unique (month_id, account_id)');
        }

        if (! $indexExists('account_balances_user_id_month_id_index')) {
            DB::statement('CREATE INDEX account_balances_user_id_month_id_index ON account_balances (user_id, month_id)');
        }

        if ($indexExists('account_balances_month_id_index')) {
            DB::statement('ALTER TABLE account_balances DROP INDEX account_balances_month_id_index');
        }
    }
};
