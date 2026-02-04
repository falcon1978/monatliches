<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->foreignId('origin_month_id')
                ->nullable()
                ->after('moved_from_month_id')
                ->constrained('months')
                ->nullOnDelete();
        });

        DB::table('entries')
            ->whereNull('origin_month_id')
            ->whereNotNull('moved_from_month_id')
            ->update([
                'origin_month_id' => DB::raw('moved_from_month_id'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_month_id');
        });
    }
};
