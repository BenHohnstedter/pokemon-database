<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Nutzereinstellungen (spec.md 2.6, 2.9, 5). */
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('go_region')->default('europa');

            /** Zähl-Toggles für den Hauptfortschrittsbalken (spec.md 2.2). */
            $table->boolean('count_regional_in_total')->default(false);
            $table->boolean('count_shiny_in_total')->default(false);

            /** Konsolenbesitz, falls aus dem Spielebesitz nicht ableitbar (spec.md 2.6). */
            $table->boolean('owns_3ds')->default(false);
            $table->boolean('owns_switch')->default(false);

            /** Retro-Farbpalette (spec.md 5): "default" | "gameboy" | "gameboy-pocket" | "crt-amber". */
            $table->string('theme')->default('default');

            /** Gamification-Optionen (spec.md 2.9). Musik standardmäßig aus. */
            $table->boolean('sound_effects_enabled')->default(true);
            $table->boolean('music_enabled')->default(false);
            $table->unsignedTinyInteger('music_volume')->default(35);
            $table->boolean('reduce_motion')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
