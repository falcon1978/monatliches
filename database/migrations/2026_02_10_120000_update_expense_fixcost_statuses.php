<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('entries')
            ->whereIn('type', ['expense', 'fixcost'])
            ->where('status', 'partial')
            ->update(['status' => 'open']);
    }

    public function down(): void
    {
        // No-op: status reversal is not deterministic.
    }
};
