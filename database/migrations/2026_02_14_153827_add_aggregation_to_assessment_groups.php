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
        Schema::table('assessment_groups', function (Blueprint $table) {
            $table->string('aggregation_mode', 30)->default('WEIGHTED_AVERAGE')->after('sort_order');
            $table->unsignedInteger('drop_count')->default(0)->after('aggregation_mode');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_groups', function (Blueprint $table) {
            $table->dropColumn(['aggregation_mode', 'drop_count']);
        });
    }
};
