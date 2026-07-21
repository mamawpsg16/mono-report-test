<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A rep deleting a prospect shouldn't erase the row -- soft delete so
     * lead history (and who removed it) stays reconstructable, matching the
     * VisitPlanEntry convention (CLAUDE.md Conventions). No unique constraint
     * on prospects needs the partial-index treatment VisitPlanEntry needed --
     * uuid is the only unique column here.
     */
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
        });
    }
};
