<?php

namespace App\Http\Controllers;

use App\Enums\GoRegion;
use App\Models\Game;
use App\Models\UserSetting;
use App\Support\PokedexFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Nutzereinstellungen: Spielebesitz, GO-Region, Zähl-Toggles, Theme, Sound
 * (spec.md 2.6, 2.9, 5).
 */
class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('settings.edit', [
            'settings' => $user->settingsOrDefault(),
            'spiele' => Game::ordered()->get()->groupBy('generation'),
            'besesseneSpiele' => $user->games()->pluck('games.id')->all(),
            'regionen' => GoRegion::options(),
            'themes' => UserSetting::THEMES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'go_region' => ['required', Rule::in(array_keys(GoRegion::options()))],
            'theme' => ['required', Rule::in(array_keys(UserSetting::THEMES))],
            // Optional: fehlt der Wert, bleibt die bisherige Größe stehen.
            'per_page' => ['nullable', Rule::in(PokedexFilter::PER_PAGE_OPTIONS)],
            'count_regional_in_total' => ['boolean'],
            'count_shiny_in_total' => ['boolean'],
            'owns_3ds' => ['boolean'],
            'owns_switch' => ['boolean'],
            'has_poke_transporter' => ['boolean'],
            'sound_effects_enabled' => ['boolean'],
            'music_enabled' => ['boolean'],
            'music_volume' => ['integer', 'min:0', 'max:100'],
            'reduce_motion' => ['boolean'],
            'profile_public' => ['boolean'],
            'spiele' => ['array'],
            'spiele.*' => ['integer', Rule::exists('games', 'id')],
        ]);

        $settings = $user->settingsOrDefault();

        $settings->update([
            'go_region' => $validated['go_region'],
            'theme' => $validated['theme'],
            'per_page' => (int) ($validated['per_page'] ?? $settings->per_page),
            // Checkboxen liefern nichts, wenn sie aus sind – deshalb explizit casten.
            'count_regional_in_total' => $request->boolean('count_regional_in_total'),
            'count_shiny_in_total' => $request->boolean('count_shiny_in_total'),
            'owns_3ds' => $request->boolean('owns_3ds'),
            'owns_switch' => $request->boolean('owns_switch'),
            'has_poke_transporter' => $request->boolean('has_poke_transporter'),
            'sound_effects_enabled' => $request->boolean('sound_effects_enabled'),
            'music_enabled' => $request->boolean('music_enabled'),
            'music_volume' => $validated['music_volume'] ?? 35,
            'reduce_motion' => $request->boolean('reduce_motion'),
        ]);

        // Die Freigabe hängt am Nutzer, nicht an den Einstellungen – sie
        // entscheidet über die Sichtbarkeit einer öffentlichen Route.
        $user->forceFill(['profile_public' => $request->boolean('profile_public')])->save();

        $user->games()->sync($validated['spiele'] ?? []);

        return back()->with('status', 'Einstellungen gespeichert. Die Dringlichkeitsstufen wurden neu berechnet.');
    }
}
