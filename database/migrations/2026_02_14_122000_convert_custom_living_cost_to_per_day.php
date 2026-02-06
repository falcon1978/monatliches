<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('holidays', 'custom_living_cost')) {
            return;
        }

        $holidays = DB::table('holidays')
            ->where('living_cost_mode', 'custom')
            ->whereNotNull('custom_living_cost')
            ->get(['id', 'date_from', 'date_to', 'custom_living_cost']);

        foreach ($holidays as $holiday) {
            $start = Carbon::parse($holiday->date_from)->startOfDay();
            $end = Carbon::parse($holiday->date_to)->startOfDay();
            $days = $start->diffInDays($end) + 1;
            if ($days <= 0) {
                continue;
            }

            $perDay = round(((float) $holiday->custom_living_cost) / $days, 2);
            DB::table('holidays')
                ->where('id', $holiday->id)
                ->update(['custom_living_cost' => $perDay]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty.
    }
};
