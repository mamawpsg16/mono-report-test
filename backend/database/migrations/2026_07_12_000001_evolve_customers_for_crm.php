<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These only made sense for CSV-imported rows; CRM customers created in
        // the UI won't have them. (Raw ALTER to avoid pulling in doctrine/dbal.)
        DB::statement('ALTER TABLE customers ALTER COLUMN original_filename DROP NOT NULL');
        DB::statement('ALTER TABLE customers ALTER COLUMN year DROP NOT NULL');

        // One customer = one company: collapse any per-year duplicates, keeping the
        // newest row. Embeddings cascade-delete with the rows we drop.
        DB::statement('
            DELETE FROM customers c
            USING customers newer
            WHERE c.customer_code = newer.customer_code
              AND c.id < newer.id
        ');

        // Swap the composite natural key for a single unique customer_code.
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_customer_code_year_unique');
        });

        Schema::table('customers', function (Blueprint $table) {
            // public identifier for URLs (never expose the sequential id)
            $table->uuid('uuid')->nullable()->after('id');
            // the owning sales rep; null = unassigned
            $table->foreignId('assigned_representative_id')->nullable()->after('country')
                ->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable()->after('assigned_representative_id');
        });

        // Backfill uuids for existing rows, then lock the column down with a DB
        // default so the Python importer's raw INSERT gets one for free too.
        DB::statement('UPDATE customers SET uuid = gen_random_uuid() WHERE uuid IS NULL');
        DB::statement('ALTER TABLE customers ALTER COLUMN uuid SET DEFAULT gen_random_uuid()');
        DB::statement('ALTER TABLE customers ALTER COLUMN uuid SET NOT NULL');

        Schema::table('customers', function (Blueprint $table) {
            $table->unique('uuid');
            $table->unique('customer_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_uuid_unique');
            $table->dropUnique('customers_customer_code_unique');
            $table->dropConstrainedForeignId('assigned_representative_id');
            $table->dropColumn(['uuid', 'notes']);
        });

        DB::statement('ALTER TABLE customers ALTER COLUMN uuid DROP DEFAULT');
        // Note: the de-dup and NOT NULL relaxations are not reversed (data-lossy).
    }
};
