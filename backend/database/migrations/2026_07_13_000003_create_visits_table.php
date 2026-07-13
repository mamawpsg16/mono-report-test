<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A visit is a rep dropping in on a customer. The field workflow (start →
     * notes → finish) lives on mobile; the web only views visit history.
     *
     * Customer-only for now: prospect visits are deferred with the rest of the
     * prospect (mobile) work, so there's no customer/prospect XOR to enforce.
     *
     * One invariant is enforced in the DB, not just the app, so it holds no
     * matter which client writes: a rep can have at most one OPEN visit at a
     * time (they can't be in two places at once).
     */
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Required: every visit is to a customer. cascadeOnDelete -- if a
            // customer row is ever hard-deleted, its visit rows go with it.
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // The visiting rep; drives scopeVisibleTo + the one-open invariant.
            // constrained('users') because the rep is a users row (matches
            // customers.assigned_representative_id). nullOnDelete so removing a
            // user doesn't wipe visit history.
            $table->foreignId('representative_id')->nullable()->constrained('users')->nullOnDelete();
            // visit_plan_entry_id is added in P4, together with its FK to the
            // visit_plan_entries table -- no FK-less columns.
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable(); // null = still open
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // At most one OPEN visit per rep, enforced by a PARTIAL unique index
        // (only rows where ended_at IS NULL are indexed). A second open visit
        // for the same rep violates the index before app code even runs.
        DB::statement('CREATE UNIQUE INDEX visits_one_open_per_representative ON visits (representative_id) WHERE ended_at IS NULL');
    }

    public function down(): void
    {
        // Dropping the table also drops its partial index.
        Schema::dropIfExists('visits');
    }
};
