<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sammlungsstand eines Nutzers fuer genau eine Form (spec.md 2.5).
 */
class UserPokemonForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'pokemon_form_id', 'owned', 'owned_shiny',
        'is_favourite', 'owned_at', 'owned_shiny_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'owned' => 'boolean',
            'owned_shiny' => 'boolean',
            'is_favourite' => 'boolean',
            'owned_at' => 'datetime',
            'owned_shiny_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(PokemonForm::class, 'pokemon_form_id');
    }
}
