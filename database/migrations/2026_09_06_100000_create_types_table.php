<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();          // pokeapi-Name, z.B. "electric"
            $table->string('name_de');
            $table->string('name_en');
            $table->string('color', 7)->default('#777777'); // Typenfarbe für das Retro-Theme
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types');
    }
};
