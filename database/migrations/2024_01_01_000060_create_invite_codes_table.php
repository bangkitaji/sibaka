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
        Schema::create('invite_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('generated_by')->constrained('users');
            $table->string('code', 32)->unique();
            $table->boolean('is_used')->default(false);
            $table->uuid('used_by')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('used_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invite_codes');
    }
};
