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
        Schema::table('holidays', function (Blueprint $table) {
            if (! Schema::hasColumn('holidays', 'living_cost_mode')) {
                $table->string('living_cost_mode')->default('deduct');
            }
            if (! Schema::hasColumn('holidays', 'custom_living_cost')) {
                $table->decimal('custom_living_cost', 10, 2)->nullable();
            }
        });

        if (Schema::hasColumn('holidays', 'deduct_living_cost')) {
            DB::table('holidays')
                ->whereNull('living_cost_mode')
                ->update(['living_cost_mode' => 'deduct']);

            DB::table('holidays')
                ->where('deduct_living_cost', 0)
                ->update(['living_cost_mode' => 'keep']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty to avoid relying on dropColumn for rollbacks.
    }
};
