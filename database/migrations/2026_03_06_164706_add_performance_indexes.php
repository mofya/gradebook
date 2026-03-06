<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_results', function (Blueprint $table) {
            $table->index('assessment_id');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->index('assessment_group_id');
            $table->index('course_id');
        });

        Schema::table('grading_scheme_levels', function (Blueprint $table) {
            $table->index('grading_scheme_id');
        });
    }

    public function down(): void
    {
        Schema::table('grade_results', function (Blueprint $table) {
            $table->dropIndex(['assessment_id']);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex(['assessment_group_id']);
            $table->dropIndex(['course_id']);
        });

        Schema::table('grading_scheme_levels', function (Blueprint $table) {
            $table->dropIndex(['grading_scheme_id']);
        });
    }
};
