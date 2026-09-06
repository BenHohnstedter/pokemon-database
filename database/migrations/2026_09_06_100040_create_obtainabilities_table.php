<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Bezugsquellen je Pokémon/Form und Spiel (spec.md 2.3). */
    public function up(): void
    {
        Schema::create('obtainabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pokemon_id')->constrained('pokemon')->cascadeOnDelete();
            $table->foreignId('pokemon_form_id')->nullable()
                ->constrained('pokemon_forms')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();

            $table->string('method')->index();
            $table->text('location_detail')->nullable();
            $table->string('difficulty')->nullable();  // überschreibt die Methoden-Basisstufe
            /** Event bereits vorbei und kommt nicht wieder (spec.md 2.7, Stufe ⚪). */
            $table->boolean('event_expired')->default(false)->index();
            $table->text('note')->nullable();
            $table->string('source')->nullable();      // "pokeapi" | "curated"
            $table->timestamps();

            $table->index(['pokemon_id', 'game_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obtainabilities');
    }
};
