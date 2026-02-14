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
            $table->string('source')->default('manual'); // 'manual', 'csv_import', 'lms_sync'
            $table->string('status')->default('enrolled'); // 'enrolled', 'withdrawn', 'deferred', 'completed'
            $table->string('exam_status')->nullable(); // 'eligible', 'debarred', 'absent', 'deferred'
            $table->decimal('ca_total', 8, 2)->nullable();
            $table->decimal('ca_override', 8, 2)->nullable();
            $table->decimal('exam_score', 8, 2)->nullable();
            $table->decimal('final_total', 8, 2)->nullable();
            $table->string('final_grade')->nullable(); // letter grade
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
