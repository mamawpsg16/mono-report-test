<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('customer_embeddings', function (Blueprint $table) {
            // one embedding per customer row; primary key doubles as the
            // uniqueness constraint python-service's UPSERT relies on
            $table->foreignId('customer_id')->primary()->constrained('customers')->cascadeOnDelete();
            $table->timestamps();
        });

        // Schema::create doesn't know the `vector` type -- pgvector adds it via
        // the extension above, so the column and its index are raw SQL.
        // 384 = fastembed's BAAI/bge-small-en-v1.5 output dimension.
        DB::statement('ALTER TABLE customer_embeddings ADD COLUMN embedding vector(384)');
        DB::statement('CREATE INDEX customer_embeddings_embedding_hnsw_idx ON customer_embeddings USING hnsw (embedding vector_cosine_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_embeddings');
    }
};
