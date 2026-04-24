<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missed_assessment_appeal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('missed_assessment_appeal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->text('reviewer_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['missed_assessment_appeal_id', 'assessment_id'], 'mappeal_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missed_assessment_appeal_items');
    }
};
