<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name_de', 'name_en', 'generation', 'platform', 'release_year',
        'home_compatible', 'bank_only', 'needs_transporter', 'still_purchasable', 'note', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'home_compatible' => 'boolean',
            'bank_only' => 'boolean',
            'needs_transporter' => 'boolean',
            'still_purchasable' => 'boolean',
            'platform' => Platform::class,
        ];
    }

    public function obtainabilities(): HasMany
    {
        return $this->hasMany(Obtainability::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('generation')->orderBy('sort_order')->orderBy('name_de');
    }

    /** Läuft auf alter Hardware im Sinne der Prioritäts-Engine (spec.md 2.7)? */
    public function isLegacyHardware(): bool
    {
        return $this->platform->isLegacyHardware();
    }
}
