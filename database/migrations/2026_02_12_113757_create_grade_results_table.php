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
        Schema::create('grade_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('raw_score', 8, 2)->nullable();
            $table->decimal('normalized_score', 8, 2)->nullable();
            $table->boolean('is_excused')->default(false);
            $table->text('notes')->nullable();
            $table->string('source')->default('manual'); // 'manual', 'csv_import', 'rapid_entry'
            $table->timestamps();

            $table->unique(['enrollment_id', 'assessment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_results');
    }
};
