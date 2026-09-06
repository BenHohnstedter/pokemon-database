<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pokemon_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pokemon_id')->constrained('pokemon')->cascadeOnDelete();
            $table->foreignId('pokemon_form_id')->nullable()
                ->constrained('pokemon_forms')->cascadeOnDelete();
            $table->foreignId('type_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot')->default(1);

            $table->unique(['pokemon_id', 'pokemon_form_id', 'type_id'], 'pokemon_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pokemon_type');
    }
};
