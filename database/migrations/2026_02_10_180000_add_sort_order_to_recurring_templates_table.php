<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_templates', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_templates', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
