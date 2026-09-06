<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wie viele Einträge im Pokédex-Raster pro Seite erscheinen.
     *
     * Als Nutzereinstellung statt nur als Query-Parameter, damit die Wahl über
     * Seitenwechsel und Sitzungen hinweg hält (spec.md 2.6, 7).
     */
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('per_page')->default(60)->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn('per_page');
        });
    }
};
