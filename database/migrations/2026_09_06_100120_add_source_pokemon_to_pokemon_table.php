<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nächste Stufe der Entwicklungslinie (inkl. der Art selbst), die einen
     * echten Fundweg hat (spec.md 2.8).
     *
     * Wird von `pokedex:recalculate` gesetzt. Ohne diese Spalte müsste die
     * Listenansicht für jede Zeile die Entwicklungskette nachladen – bei 1.300+
     * Arten pro Seitenaufruf zu teuer (spec.md 7, Performance).
     */
    public function up(): void
    {
        Schema::table('pokemon', function (Blueprint $table) {
            $table->foreignId('source_pokemon_id')->nullable()->after('evolves_from_id')
                ->constrained('pokemon')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pokemon', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_pokemon_id');
        });
    }
};
