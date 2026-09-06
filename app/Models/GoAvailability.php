<?php

namespace App\Models;

use App\Enums\GoMethod;
use App\Enums\GoRegion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoAvailability extends Model
{
    use HasFactory;

    protected $table = 'go_availabilities';

    protected $fillable = [
        'pokemon_id', 'pokemon_form_id', 'method', 'regions',
        'transferable_to_home', 'note',
    ];

    protected function casts(): array
    {
        return [
            'regions' => 'array',
            'transferable_to_home' => 'boolean',
            'method' => GoMethod::class,
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

    /** Spawnt es in der Region des Nutzers (spec.md 2.4)? */
    public function availableInRegion(GoRegion|string|null $region): bool
    {
        if (! $this->method->isAvailable()) {
            return false;
        }

        $regions = $this->regions ?? [];

        if ($regions === [] || in_array(GoRegion::Weltweit->value, $regions, true)) {
            return true;
        }

        $value = $region instanceof GoRegion ? $region->value : $region;

        return $value !== null && in_array($value, $regions, true);
    }

    public function isRegionExclusive(): bool
    {
        $regions = $this->regions ?? [];

        return $regions !== [] && ! in_array(GoRegion::Weltweit->value, $regions, true);
    }

    /** Regionsnamen als lesbarer String fürs UI. */
    public function regionLabels(): string
    {
        return collect($this->regions ?? [])
            ->map(fn (string $r) => GoRegion::tryFrom($r)?->label() ?? $r)
            ->join(', ');
    }
}
