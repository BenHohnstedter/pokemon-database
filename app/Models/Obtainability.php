<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\ObtainMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Obtainability extends Model
{
    use HasFactory;

    protected $table = 'obtainabilities';

    protected $fillable = [
        'pokemon_id', 'pokemon_form_id', 'game_id', 'method',
        'location_detail', 'locations', 'difficulty', 'event_expired', 'note', 'source',
    ];

    protected function casts(): array
    {
        return [
            'event_expired' => 'boolean',
            'method' => ObtainMethod::class,
            'difficulty' => Difficulty::class,
            'locations' => 'array',
        ];
    }

    public function pokemon(): BelongsTo
    {
        return $this->belongsTo(Pokemon::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(PokemonForm::class, 'pokemon_form_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** Hinterlegte Schwierigkeit, sonst die Basisstufe der Methode. */
    public function effectiveDifficulty(): Difficulty
    {
        if ($this->event_expired) {
            return Difficulty::SehrSchwer;
        }

        return $this->difficulty ?? $this->method->baseDifficulty();
    }

    /** Ein Weg, der das Pokémon tatsächlich neu in die Sammlung bringt. */
    public function isUsableSource(): bool
    {
        return ! $this->event_expired && $this->method !== ObtainMethod::TransferOnly;
    }

    /**
     * Einzelne Gebiete dieser Quelle, wenn sie strukturiert vorliegen.
     *
     * Nur `pokedex:import-encounters` füllt das Feld – CSV- und kuratierte
     * Zeilen tragen ausschließlich den Anzeigetext in `location_detail` und
     * bekommen deshalb keine Links.
     *
     * @return array<int,array{slug:string,name_de:string}>
     */
    public function locationAreas(): array
    {
        if (! is_array($this->locations)) {
            return [];
        }

        return $this->locations['areas'] ?? [];
    }

    /** Wurde die Ortsliste zugunsten der Lesbarkeit gekürzt ("u.a.")? */
    public function locationsTruncated(): bool
    {
        if (! is_array($this->locations)) {
            return false;
        }

        return (bool) ($this->locations['truncated'] ?? false);
    }

    /** PokéWiki-Artikel zu einem deutschen Ortsnamen – externe Fundort-Details. */
    public function locationWikiUrl(string $name): string
    {
        return 'https://www.pokewiki.de/'.rawurlencode(str_replace(' ', '_', $name));
    }
}
