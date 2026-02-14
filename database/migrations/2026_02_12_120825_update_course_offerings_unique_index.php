<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropUnique(['course_id', 'semester_id']);
            $table->unique(['course_id', 'semester_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropUnique(['course_id', 'semester_id', 'section']);
            $table->unique(['course_id', 'semester_id']);
        });
    }
};
