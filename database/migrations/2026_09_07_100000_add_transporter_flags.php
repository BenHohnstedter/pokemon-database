<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Poké Transporter als eigene Hürde auf dem Weg nach Pokémon Bank.
 *
 * Nicht jedes Bank-Spiel hängt gleich direkt an Bank: Gen 6 und 7 laden ihre
 * Pokémon selbst hoch, alles Ältere braucht zusätzlich die 3DS-App
 * "Poké Transporter" (Gen 1/2 aus der Virtual Console und Gen 5 direkt, Gen 3
 * und 4 am Ende ihrer Transferkette). Wer die App nicht hat, kommt aus diesen
 * Titeln gar nicht nach HOME – für den ist die Bank-Frist dort gegenstandslos,
 * weil der Weg ohnehin verschlossen ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->boolean('needs_transporter')->default(false)->after('bank_only');
        });

        Schema::table('user_settings', function (Blueprint $table) {
            // Standard true: wer Bank genutzt hat, hat die App in aller Regel
            // schon installiert. Wer nicht, schaltet es in den Einstellungen ab.
            $table->boolean('has_poke_transporter')->default(true)->after('owns_switch');
        });
    }

    public function down(): void
    {
        Schema::table('games', fn (Blueprint $table) => $table->dropColumn('needs_transporter'));
        Schema::table('user_settings', fn (Blueprint $table) => $table->dropColumn('has_poke_transporter'));
    }
};
