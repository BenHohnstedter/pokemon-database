<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eine Zeile pro dauerhaft in HOME speicherbarer Form (spec.md 2.2).
     * Jedes Pokémon hat mindestens eine Zeile mit form_type = base.
     * Shiny ist KEINE eigene Zeile, sondern eine zweite Besitz-Spalte je Form –
     * jede Form kann normal und/oder shiny im Bestand sein.
     */
    public function up(): void
    {
        Schema::create('pokemon_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pokemon_id')->constrained('pokemon')->cascadeOnDelete();
            $table->string('slug')->unique();                 // z.B. "raichu-alola"
            $table->string('name_de');
            $table->string('name_en');
            $table->string('form_type')->default('base')->index();  // base|regional|other
            $table->string('region')->nullable()->index();          // alola|galar|hisui|paldea
            $table->boolean('is_default')->default(false)->index();

            $table->string('sprite_url')->nullable();
            $table->string('shiny_sprite_url')->nullable();
            $table->string('artwork_url')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['pokemon_id', 'form_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pokemon_forms');
    }
};
