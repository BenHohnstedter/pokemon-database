<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Hintergrundmusik ist wieder raus (FEATURE-UPDATES.md 19), damit auch ihre
 * beiden Einstellungen. Die 8-Bit-Effekte bleiben — die hängen an
 * `sound_effects_enabled` und sind davon nicht betroffen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn(['music_enabled', 'music_volume']);
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->boolean('music_enabled')->default(false);
            $table->unsignedTinyInteger('music_volume')->default(35);
        });
    }
};
