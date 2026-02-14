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
        Schema::table('grading_schemes', function (Blueprint $table) {
            $table->integer('rounding_precision')->default(0)->after('decimal_places');
            $table->string('boundary_behavior')->default('inclusive_lower')->after('rounding_precision');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grading_schemes', function (Blueprint $table) {
            $table->dropColumn(['rounding_precision', 'boundary_behavior']);
        });
    }
};
