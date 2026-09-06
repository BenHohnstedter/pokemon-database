<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Pokémon-GO-Verfügbarkeit inkl. Regionalexklusivität (spec.md 2.4). */
    public function up(): void
    {
        Schema::create('go_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pokemon_id')->constrained('pokemon')->cascadeOnDelete();
            $table->foreignId('pokemon_form_id')->nullable()
                ->constrained('pokemon_forms')->cascadeOnDelete();

            $table->string('method')->index();
            /** Liste von GoRegion-Werten; ["weltweit"] = keine Einschränkung. */
            $table->json('regions');
            $table->boolean('transferable_to_home')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['pokemon_id', 'pokemon_form_id'], 'go_availability_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('go_availabilities');
    }
};
