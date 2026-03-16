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
        Schema::create('unmatched_lab_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('github_username')->index();
            $table->json('row_data');
            $table->string('status')->default('pending');
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('matched_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_offering_id', 'assessment_id', 'github_username'], 'unmatched_unique');
            $table->index(['github_username', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unmatched_lab_grades');
    }
};
