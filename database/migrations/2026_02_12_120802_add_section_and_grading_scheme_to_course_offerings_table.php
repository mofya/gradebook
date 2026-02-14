<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->string('section')->nullable()->after('semester_id');
            $table->foreignId('grading_scheme_id')->nullable()->after('section')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropForeign(['grading_scheme_id']);
            $table->dropColumn(['section', 'grading_scheme_id']);
        });
    }
};
