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
        Schema::create('assessment_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('ca');
            $table->decimal('weight_percentage', 5, 2);
            $table->decimal('weight_points', 8, 2)->nullable();
            $table->string('weight_mode')->default('percentage');
            $table->integer('sort_order')->default(0);
            $table->string('aggregation_mode', 30)->default('WEIGHTED_AVERAGE');
            $table->unsignedInteger('drop_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_groups');
    }
};
