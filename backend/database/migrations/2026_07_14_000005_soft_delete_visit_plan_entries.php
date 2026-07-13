<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A rep removing a planned visit shouldn't erase the row -- soft delete
     * so plans stay reconstructable (who planned what, who un-planned it).
     * deleted_by is the "who"; deleted_at (added by softDeletes()) is the
     * "when". The plain UNIQUE(visit_plan_id, customer_id, planned_date) from
     * the create migration doesn't know about deleted_at, so a soft-deleted
     * row would still block re-adding the same customer/day -- swapped for a
     * partial index that only applies to live rows, same technique as
     * visits_one_open_per_representative.
     */
    public function up(): void
    {
        Schema::table('visit_plan_entries', function (Blueprint $table) {
            $table->dropUnique(['visit_plan_id', 'customer_id', 'planned_date']);
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')
                ->constrained('users')->nullOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX visit_plan_entries_unique_live ON visit_plan_entries (visit_plan_id, customer_id, planned_date) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX visit_plan_entries_unique_live');

        Schema::table('visit_plan_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
            $table->unique(['visit_plan_id', 'customer_id', 'planned_date']);
        });
    }
};
