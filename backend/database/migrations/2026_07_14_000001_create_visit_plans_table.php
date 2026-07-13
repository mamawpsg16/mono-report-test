<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One visit plan per rep per week (the Monday that week starts on).
     * Entries live in visit_plan_entries; this row is just the "week" container.
     */
    public function up(): void
    {
        Schema::create('visit_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('representative_id')->constrained('users')->cascadeOnDelete();
            $table->date('week_start_date'); // always a Monday; enforced in the service
            $table->timestamps();

            // One plan per rep per week -- re-requesting "this week's plan"
            // must find the same row, not create duplicates.
            $table->unique(['representative_id', 'week_start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_plans');
    }
};
