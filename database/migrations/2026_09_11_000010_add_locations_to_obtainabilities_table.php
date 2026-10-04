<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Strukturierte Ortsdaten für verlinkbare Fundorte.
     *
     * `location_detail` bleibt als reiner Anzeigetext bestehen (auch für CSV-,
     * Kuratierte- und Fallback-Zeilen ohne Orte). PokeAPI-Quellen tragen hier
     * zusätzlich die einzelnen Gebiete mit Slug und deutschem Namen, damit sich
     * jeder Ort im PokéWiki nachschlagen lässt.
     */
    public function up(): void
    {
        Schema::table('obtainabilities', function (Blueprint $table) {
            $table->json('locations')->nullable()->after('location_detail');
        });
    }

    public function down(): void
    {
        Schema::table('obtainabilities', function (Blueprint $table) {
            $table->dropColumn('locations');
        });
    }
};
