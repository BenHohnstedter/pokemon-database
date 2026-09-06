<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_de');
            $table->string('name_en');
            $table->unsignedTinyInteger('generation')->index();
            $table->string('platform')->index();
            $table->unsignedSmallInteger('release_year')->nullable();

            /** Kann direkt (ohne Bank) nach Pokémon HOME übertragen? */
            $table->boolean('home_compatible')->default(false)->index();
            /** Der Weg nach HOME führt ausschließlich über Pokémon Bank. */
            $table->boolean('bank_only')->default(false)->index();
            /** Noch regulär im Handel/eShop erhältlich (spec.md 2.7, Stufe 🟡). */
            $table->boolean('still_purchasable')->default(false)->index();

            $table->string('note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
