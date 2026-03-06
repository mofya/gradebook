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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('manual');
            $table->string('status')->default('enrolled');
            $table->string('study_mode', 20)->default('REGULAR');
            $table->string('exam_status')->nullable();
            $table->decimal('ca_total', 8, 2)->nullable();
            $table->decimal('ca_override', 8, 2)->nullable();
            $table->text('ca_override_reason')->nullable();
            $table->decimal('exam_score', 8, 2)->nullable();
            $table->decimal('final_total', 8, 2)->nullable();
            $table->decimal('final_override', 8, 2)->nullable();
            $table->text('final_override_reason')->nullable();
            $table->string('final_grade')->nullable();
            $table->decimal('grade_points', 3, 1)->nullable();
            $table->text('remarks')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'course_offering_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
