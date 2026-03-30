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
        Schema::table('students', function (Blueprint $table) {
            $table->string('personal_email')->nullable()->unique()->after('email');
            $table->string('password')->nullable()->after('personal_email');
            $table->timestamp('registered_at')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['personal_email', 'password', 'registered_at']);
        });
    }
};
