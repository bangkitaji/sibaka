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
        Schema::create('contents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('author_id')->constrained('users');
            $table->string('title', 200);
            $table->text('body');
            $table->text('body_html');
            $table->string('category');
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_qna')->default(false);
            $table->uuid('accepted_solution_id')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('author_id');
            $table->index('category');
            $table->index('status');
            $table->index('published_at');
            $table->index('is_locked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
