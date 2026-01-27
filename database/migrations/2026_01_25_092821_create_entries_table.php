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
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('month_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date')->default(DB::raw('CURRENT_DATE'));
            $table->enum('type', ['income', 'expense', 'fixcost', 'transfer']);
            $table->enum('direction', ['in', 'out']);
            $table->decimal('amount', 10, 2);
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['open', 'partial', 'paid'])->default('open');
            $table->string('description');
            $table->foreignId('related_entry_id')->nullable()->constrained('entries')->nullOnDelete();
            $table->uuid('transfer_group_id')->nullable();
            $table->foreignId('recurring_template_id')->nullable()->constrained('recurring_templates')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
