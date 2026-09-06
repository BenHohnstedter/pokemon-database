<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Trainer-Level/XP und Login-Streak (spec.md 2.9). */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('xp')->default(0)->index();
            $table->unsignedSmallInteger('login_streak')->default(0);
            $table->date('last_login_date')->nullable();
            $table->boolean('profile_public')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['xp', 'login_streak', 'last_login_date', 'profile_public']);
        });
    }
};
