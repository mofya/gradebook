<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missed_assessment_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('narrative');
            $table->text('other_notes')->nullable();
            $table->boolean('dean_confirmed')->default(false);
            $table->string('evidence_path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['course_offering_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missed_assessment_appeals');
    }
};
