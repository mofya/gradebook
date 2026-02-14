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
        Schema::table('grade_queries', function (Blueprint $table) {
            $table->string('subject')->after('assessment_id');
            $table->string('priority')->default('normal')->after('status');
            $table->foreignId('assigned_to')->nullable()->after('priority')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grade_queries', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['subject', 'priority', 'assigned_to']);
        });
    }
};
