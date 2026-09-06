<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\FormType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pokemon extends Model
{
    use HasFactory;

    /** Ohne das würde Eloquent "pokemons" erwarten. */
    protected $table = 'pokemon';

    protected $fillable = [
        'dex_nr', 'slug', 'name_de', 'name_en', 'generation',
        'is_legendary', 'is_mythical', 'is_baby',
        'evolution_chain_id', 'evolves_from_id', 'source_pokemon_id', 'evolution_trigger',
        'evolution_conditions', 'evolution_summary_de',
        'difficulty', 'obtainable_directly',
        'base_stats', 'height', 'weight',
    ];

    protected function casts(): array
    {
        return [
            'is_legendary' => 'boolean',
            'is_mythical' => 'boolean',
            'is_baby' => 'boolean',
            'obtainable_directly' => 'boolean',
            'evolution_conditions' => 'array',
            'base_stats' => 'array',
            'difficulty' => Difficulty::class,
        ];
    }

    public function forms(): HasMany
    {
        return $this->hasMany(PokemonForm::class)->orderBy('sort_order');
    }

    public function baseForm(): HasOne
    {
        return $this->hasOne(PokemonForm::class)->where('form_type', FormType::Base->value);
    }

    public function regionalForms(): HasMany
    {
        return $this->hasMany(PokemonForm::class)->where('form_type', FormType::Regional->value);
    }

    public function types(): BelongsToMany
    {
        return $this->belongsToMany(Type::class, 'pokemon_type')
            ->withPivot(['slot', 'pokemon_form_id'])
            ->orderBy('pokemon_type.slot');
    }

    public function obtainabilities(): HasMany
    {
        return $this->hasMany(Obtainability::class);
    }

    public function goAvailability(): HasOne
    {
        return $this->hasOne(GoAvailability::class)->whereNull('pokemon_form_id');
    }

    public function goAvailabilities(): HasMany
    {
        return $this->hasMany(GoAvailability::class);
    }

    public function evolvesFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'evolves_from_id');
    }

    public function evolvesTo(): HasMany
    {
        return $this->hasMany(self::class, 'evolves_from_id');
    }

    /**
     * Die Stufe der Linie, über die dieses Pokémon tatsächlich beschafft wird –
     * bei nur-durch-Entwicklung-Arten also die fangbare Vorstufe.
     * Wird von `pokedex:recalculate` gepflegt.
     */
    public function sourcePokemon(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_pokemon_id');
    }

    /** Ist es ausschließlich über eine Vorstufe erreichbar? */
    public function onlyViaEvolution(): bool
    {
        return ! $this->obtainable_directly
            && $this->source_pokemon_id !== null
            && $this->source_pokemon_id !== $this->id;
    }

    /** Alle Pokémon derselben Entwicklungslinie, inklusive dieses hier. */
    public function evolutionLine(): Builder
    {
        return self::query()
            ->where('evolution_chain_id', $this->evolution_chain_id)
            ->orderBy('dex_nr');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        if (ctype_digit($term)) {
            return $query->where('dex_nr', (int) $term);
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name_de', 'like', "%{$term}%")
                ->orWhere('name_en', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%");
        });
    }

    public function getRouteKeyName(): string
    {
        return 'dex_nr';
    }

    /** "#0025" – für die Retro-Anzeige. */
    public function getDexLabelAttribute(): string
    {
        return '#'.str_pad((string) $this->dex_nr, 4, '0', STR_PAD_LEFT);
    }
}
