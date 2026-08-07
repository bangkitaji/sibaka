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
        Schema::create('drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('content_id')->nullable()->constrained('contents')->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users');
            $table->text('body');
            $table->timestamp('saved_at');
            $table->timestamps();

            $table->unique('content_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drafts');
    }
};
