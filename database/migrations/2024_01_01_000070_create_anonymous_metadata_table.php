<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('anonymous_metadata', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('content_id')->constrained('contents')->cascadeOnDelete();
            $table->uuid('author_id');
            $table->string('ip_hash');
            $table->text('user_agent');
            $table->string('browser_fingerprint')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
            $table->unique('content_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anonymous_metadata');
    }
};
