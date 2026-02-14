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
            $table->renameColumn('weight', 'weight_percentage');
        });

        Schema::table('assessment_groups', function (Blueprint $table) {
            $table->decimal('weight_points', 8, 2)->nullable()->after('weight_percentage');
            $table->string('weight_mode')->default('percentage')->after('weight_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessment_groups', function (Blueprint $table) {
            $table->dropColumn(['weight_points', 'weight_mode']);
        });

        Schema::table('assessment_groups', function (Blueprint $table) {
            $table->renameColumn('weight_percentage', 'weight');
        });
    }
};
