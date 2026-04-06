<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->string('verification_token', 64)->nullable()->unique()->after('published_at');
            $table->dateTime('verification_expires_at')->nullable()->after('verification_token');
        });
    }

    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropUnique(['verification_token']);
            $table->dropColumn(['verification_token', 'verification_expires_at']);
        });
    }
};
