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
        // Full-text search GIN index on contents (title + body)
        DB::statement("
            CREATE INDEX contents_fulltext_idx
            ON contents
            USING GIN (to_tsvector('english', coalesce(title, '') || ' ' || coalesce(body, '')))
        ");

        // Full-text search GIN index on profiles (job_title + company + primary_tech_stack)
        DB::statement("
            CREATE INDEX profiles_fulltext_idx
            ON profiles
            USING GIN (to_tsvector('english', coalesce(job_title, '') || ' ' || coalesce(company, '') || ' ' || coalesce(primary_tech_stack, '')))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS contents_fulltext_idx');
        DB::statement('DROP INDEX IF EXISTS profiles_fulltext_idx');
    }
};
