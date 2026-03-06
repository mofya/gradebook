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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('weight', 5, 2);
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('assessment_group_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('max_raw_score', 8, 2)->default(100);
            $table->decimal('normalized_to', 8, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('has_subsections')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
