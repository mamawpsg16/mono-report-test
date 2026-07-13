<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A dedicated counter for auto-generated customer_code values, separate
     * from customers_id_seq. Unlike id -- consumed by every customer row,
     * imported or not -- this only advances when a code is actually
     * generated, so it starts clean at 1 regardless of existing row count.
     * Like any sequence, it's non-transactional (a rolled-back test still
     * consumes a value), so gaps over time are normal, not a bug.
     *
     * IF NOT EXISTS / IF EXISTS: RefreshDatabase resets the test DB via
     * Schema::dropAllTables() + re-running every up() -- it drops TABLES,
     * not standalone objects like a sequence, so a second fresh-refresh
     * cycle would otherwise hit "already exists" on this exact line.
     */
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS customer_codes_seq START 1');
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS customer_codes_seq');
    }
};
