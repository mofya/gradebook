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
        Schema::create('subsection_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_subsection_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 8, 2);
            $table->timestamps();

            $table->unique(['grade_result_id', 'assessment_subsection_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subsection_scores');
    }
};
