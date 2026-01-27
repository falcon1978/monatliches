<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->string('income_source')->nullable()->index()->after('type');
        });

        DB::table('entries')
            ->join('accounts', 'entries.account_id', '=', 'accounts.id')
            ->where('entries.type', 'income')
            ->whereNull('entries.recurring_template_id')
            ->update([
                'entries.income_source' => DB::raw("CASE WHEN accounts.type = 'forecast' THEN 'expected' ELSE 'manual' END"),
            ]);
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropIndex(['income_source']);
            $table->dropColumn('income_source');
        });
    }
};
