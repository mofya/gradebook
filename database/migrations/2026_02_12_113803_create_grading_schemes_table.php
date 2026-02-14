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
        Schema::create('grading_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "UNZA Default Scale"
            $table->boolean('is_default')->default(false);
            $table->string('rounding_rule')->default('round'); // 'round', 'floor', 'ceil'
            $table->integer('decimal_places')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_schemes');
    }
};
