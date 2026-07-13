<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * customer_code, email and original_filename were NOT NULL as import/
     * rewards-era assumptions. CRM-native customers -- converted from a prospect
     * or created by hand -- legitimately have no source file, no spreadsheet
     * code, and maybe no email yet. Relax all three to nullable so those rows
     * are valid. Imported rows still supply customer_code (the ON CONFLICT key)
     * on every row, so the upsert is unaffected; Postgres allows multiple NULLs
     * under the existing UNIQUE(customer_code).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE customers ALTER COLUMN original_filename DROP NOT NULL');
        DB::statement('ALTER TABLE customers ALTER COLUMN customer_code DROP NOT NULL');
        DB::statement('ALTER TABLE customers ALTER COLUMN email DROP NOT NULL');
    }

    /**
     * Re-adding NOT NULL fails if any CRM-created row left these null -- that
     * needs a conscious backfill, not a silent migration. Same caveat the
     * original email-NOT-NULL migration carried.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE customers ALTER COLUMN email SET NOT NULL');
        DB::statement('ALTER TABLE customers ALTER COLUMN customer_code SET NOT NULL');
        DB::statement('ALTER TABLE customers ALTER COLUMN original_filename SET NOT NULL');
    }
};
