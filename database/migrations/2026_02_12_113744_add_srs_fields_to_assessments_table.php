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
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('assessment_group_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->decimal('max_raw_score', 8, 2)->default(100)->after('weight');
            $table->decimal('normalized_to', 8, 2)->nullable()->after('max_raw_score');
            $table->date('due_date')->nullable()->after('normalized_to');
            $table->boolean('has_subsections')->default(false)->after('due_date');
            $table->boolean('is_published')->default(false)->after('has_subsections');
            $table->timestamp('published_at')->nullable()->after('is_published');
            $table->integer('sort_order')->default(0)->after('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropForeign(['assessment_group_id']);
            $table->dropColumn([
                'assessment_group_id',
                'max_raw_score',
                'normalized_to',
                'due_date',
                'has_subsections',
                'is_published',
                'published_at',
                'sort_order',
            ]);
        });
    }
};
