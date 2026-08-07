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
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('moderator_id')->constrained('users');
            $table->uuid('target_user_id')->nullable();
            $table->uuid('target_content_id')->nullable();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
        });

        // PostgreSQL immutability trigger: prevent UPDATE and DELETE on moderation_logs
        DB::unprepared('
            CREATE OR REPLACE FUNCTION prevent_moderation_log_mutation()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION \'moderation_logs table is immutable: % operations are not allowed\', TG_OP;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER moderation_logs_immutable_trigger
            BEFORE UPDATE OR DELETE ON moderation_logs
            FOR EACH ROW
            EXECUTE FUNCTION prevent_moderation_log_mutation();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('
            DROP TRIGGER IF EXISTS moderation_logs_immutable_trigger ON moderation_logs;
            DROP FUNCTION IF EXISTS prevent_moderation_log_mutation();
        ');

        Schema::dropIfExists('moderation_logs');
    }
};
