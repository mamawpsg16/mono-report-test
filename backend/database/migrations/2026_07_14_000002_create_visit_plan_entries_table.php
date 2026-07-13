<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One planned customer visit on one day of a plan's week. Day-only
     * (planned_date), no time -- "the calendar should be simple." Prospects
     * are never plan entries (out of scope per P3); a plan entry always
     * targets a real Customer.
     */
    public function up(): void
    {
        Schema::create('visit_plan_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('visit_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('planned_date');
            $table->timestamps();

            // No planning the same customer twice on the same day.
            $table->unique(['visit_plan_id', 'customer_id', 'planned_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_plan_entries');
    }
};
