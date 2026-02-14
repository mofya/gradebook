<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('student_id_number')->nullable()->unique()->after('id');
            $table->string('gender')->nullable()->after('email');
            $table->string('program')->nullable()->after('gender');
            $table->integer('year_of_study')->nullable()->after('program');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['student_id_number']);
            $table->dropColumn(['student_id_number', 'gender', 'program', 'year_of_study']);
        });
    }
};
