<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sammlungsstand je Nutzer und Form (spec.md 2.5).
     *
     * Abweichung von spec.md Abschnitt 3: dort war UserOwnership mit
     * form_type = base|regional|shiny skizziert. Da Pokémon mit mehreren
     * Regionalformen (z.B. Mauzi: Alola + Galar) sonst nicht getrennt
     * abbildbar wären, hängt der Besitz hier an der konkreten Form, und
     * "shiny" ist eine zweite Spalte statt eines dritten Typs.
     * Protokolliert in .claude/FEATURE-UPDATES.md.
     */
    public function up(): void
    {
        Schema::create('user_pokemon_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pokemon_form_id')->constrained()->cascadeOnDelete();

            $table->boolean('owned')->default(false)->index();
            $table->boolean('owned_shiny')->default(false)->index();
            $table->boolean('is_favourite')->default(false)->index();

            $table->timestamp('owned_at')->nullable()->index();
            $table->timestamp('owned_shiny_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'pokemon_form_id'], 'user_form_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_pokemon_forms');
    }
};
