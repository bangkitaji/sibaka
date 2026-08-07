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
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('job_title', 100)->nullable();
            $table->string('company', 100)->nullable();
            $table->integer('years_of_experience')->nullable();
            $table->string('primary_tech_stack', 200)->nullable();
            $table->string('secondary_tech_stack', 200)->nullable();
            $table->string('mentorship_status')->nullable();
            $table->string('hiring_status')->nullable();
            $table->string('availability')->nullable();
            $table->string('linkedin_url', 200)->nullable();
            $table->string('github_url', 200)->nullable();
            $table->integer('completion_percentage')->default(0);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
