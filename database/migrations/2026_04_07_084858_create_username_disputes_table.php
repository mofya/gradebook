<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('username_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claimant_student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('current_holder_student_id')->constrained('students')->cascadeOnDelete();
            $table->string('github_username');
            $table->foreignId('course_offering_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('username_disputes');
    }
};
