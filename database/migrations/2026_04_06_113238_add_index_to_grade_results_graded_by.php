<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_results', function (Blueprint $table) {
            $table->index('graded_by');
        });
    }

    public function down(): void
    {
        Schema::table('grade_results', function (Blueprint $table) {
            $table->dropIndex(['graded_by']);
        });
    }
};
