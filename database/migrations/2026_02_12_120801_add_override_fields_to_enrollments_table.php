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
        Schema::table('enrollments', function (Blueprint $table) {
            $table->text('ca_override_reason')->nullable()->after('ca_override');
            $table->decimal('final_override', 8, 2)->nullable()->after('final_total');
            $table->text('final_override_reason')->nullable()->after('final_override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['ca_override_reason', 'final_override', 'final_override_reason']);
        });
    }
};
