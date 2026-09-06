<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pokemon', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('dex_nr')->unique();   // Nationale Dex-Nummer
            $table->string('slug')->unique();                   // pokeapi-Name, z.B. "bulbasaur"
            $table->string('name_de');
            $table->string('name_en');
            $table->unsignedTinyInteger('generation')->index();

            $table->boolean('is_legendary')->default(false);
            $table->boolean('is_mythical')->default(false);
            $table->boolean('is_baby')->default(false);

            // Entwicklungskette (spec.md 2.1)
            $table->unsignedInteger('evolution_chain_id')->nullable()->index();
            $table->foreignId('evolves_from_id')->nullable()
                ->constrained('pokemon')->nullOnDelete();
            $table->string('evolution_trigger')->nullable();    // level-up, use-item, trade …
            $table->json('evolution_conditions')->nullable();   // Level, Stein, Tageszeit, Ort …
            $table->string('evolution_summary_de')->nullable(); // vorgerenderter Klartext fürs UI

            // Beschaffung (spec.md 2.8)
            $table->string('difficulty')->default('leicht')->index();
            $table->boolean('obtainable_directly')->default(true)->index();

            $table->json('base_stats')->nullable();
            $table->unsignedSmallInteger('height')->nullable();  // Dezimeter
            $table->unsignedSmallInteger('weight')->nullable();  // Hektogramm

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pokemon');
    }
};
