<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Assumes no existing customers row has a NULL email. This will fail
        // loudly (by design) if that assumption is ever wrong — the fix is then
        // a conscious backfill/cleanup, not a silent data change here.
        DB::statement('ALTER TABLE customers ALTER COLUMN email SET NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE customers ALTER COLUMN email DROP NOT NULL');
    }
};
