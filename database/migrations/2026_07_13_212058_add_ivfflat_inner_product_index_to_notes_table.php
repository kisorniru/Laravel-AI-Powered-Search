<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('notes')) {
            return;
        }

        // IVFFlat is an ANN index. For Inner Product search, pgvector needs vector_ip_ops.
        // lists controls how many inverted lists/clusters pgvector builds.
        DB::statement('CREATE INDEX IF NOT EXISTS notes_embedding_ivfflat_inner_product_index ON notes USING ivfflat (embedding vector_ip_ops) WITH (lists = 10)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS notes_embedding_ivfflat_inner_product_index');
    }
};
