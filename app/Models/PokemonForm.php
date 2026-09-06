<?php

namespace App\Models;

use App\Enums\FormType;
use App\Enums\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PokemonForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'pokemon_id', 'slug', 'name_de', 'name_en', 'form_type', 'region',
        'is_default', 'sprite_url', 'shiny_sprite_url', 'artwork_url', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'form_type' => FormType::class,
            'region' => Region::class,
        ];
    }

    public function pokemon(): BelongsTo
    {
        return $this->belongsTo(Pokemon::class);
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(UserPokemonForm::class);
    }

    public function scopeBase(Builder $query): Builder
    {
        return $query->where('form_type', FormType::Base->value);
    }

    public function scopeRegional(Builder $query): Builder
    {
        return $query->where('form_type', FormType::Regional->value);
    }

    /** Bildquelle mit Fallback auf das Artwork des Pokémon. */
    public function displayImage(bool $shiny = false): ?string
    {
        if ($shiny) {
            return $this->shiny_sprite_url ?: $this->sprite_url ?: $this->artwork_url;
        }

        return $this->artwork_url ?: $this->sprite_url;
    }
}
