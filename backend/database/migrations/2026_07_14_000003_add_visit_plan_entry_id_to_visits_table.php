<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deferred from the P3 visits migration until visit_plan_entries existed
     * (this codebase adds FK-backed columns only once their target table is
     * real). Set automatically when a visit starts on mobile and matches a
     * planned entry for that customer/day -- never set from the web.
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->foreignId('visit_plan_entry_id')->nullable()
                ->after('representative_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visit_plan_entry_id');
        });
    }
};
