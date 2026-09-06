<?php

namespace App\Models;

use App\Enums\GoRegion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    use HasFactory;

    /** Auswählbare Retro-Farbpaletten (spec.md 5). */
    public const THEMES = [
        'default' => 'Dex-Rescue (Standard)',
        'gameboy' => 'Game Boy Grün',
        'gameboy-pocket' => 'Game Boy Pocket (Graustufen)',
        'crt-amber' => 'CRT Bernstein',
    ];

    protected $fillable = [
        'user_id', 'go_region',
        'count_regional_in_total', 'count_shiny_in_total',
        'owns_3ds', 'owns_switch', 'theme', 'per_page',
        'sound_effects_enabled', 'music_enabled', 'music_volume', 'reduce_motion',
    ];

    protected $attributes = [
        'go_region' => 'europa',
        'count_regional_in_total' => false,
        'count_shiny_in_total' => false,
        'theme' => 'default',
        'per_page' => 60,
        'sound_effects_enabled' => true,
        'music_enabled' => false,
        'music_volume' => 35,
    ];

    protected function casts(): array
    {
        return [
            'count_regional_in_total' => 'boolean',
            'count_shiny_in_total' => 'boolean',
            'owns_3ds' => 'boolean',
            'owns_switch' => 'boolean',
            'sound_effects_enabled' => 'boolean',
            'music_enabled' => 'boolean',
            'reduce_motion' => 'boolean',
            'go_region' => GoRegion::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function themeLabel(): string
    {
        return self::THEMES[$this->theme] ?? self::THEMES['default'];
    }
}
