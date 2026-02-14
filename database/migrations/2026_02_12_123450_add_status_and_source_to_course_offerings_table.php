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
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('exam_weight');
            $table->foreignId('created_from_offering_id')->nullable()->after('status')
                ->constrained('course_offerings')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropForeign(['created_from_offering_id']);
            $table->dropColumn(['status', 'created_from_offering_id']);
        });
    }
};
