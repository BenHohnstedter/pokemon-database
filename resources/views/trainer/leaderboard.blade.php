<x-app-layout>
    <x-slot name="title">Bestenliste – {{ config('app.name') }}</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="font-pixel text-sm text-dex-accent sm:text-base">Bestenliste</h1>
                <p class="mt-2 text-sm text-dex-muted">
                    Rangliste nach Trainer-XP.
                    @if ($eigenerRang !== false)
                        Du stehst aktuell auf Platz {{ $eigenerRang + 1 }}.
                    @endif
                </p>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('trainer.leaderboard') }}"
                   @class(['pixel-button' => ! $nurFreunde, 'pixel-button-ghost' => $nurFreunde])>
                    Alle
                </a>
                <a href="{{ route('trainer.leaderboard', ['freunde' => 1]) }}"
                   @class(['pixel-button' => $nurFreunde, 'pixel-button-ghost' => ! $nurFreunde])>
                    Nur Freunde
                </a>
            </div>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="pixel-panel overflow-x-auto p-5 lg:col-span-2">
            <table class="w-full min-w-[30rem] text-left text-sm">
                <thead class="border-b-2 border-dex-border text-[10px] uppercase text-dex-muted">
                    <tr>
                        <th class="py-2 pr-3">#</th>
                        <th class="py-2 pr-3">Trainer</th>
                        <th class="py-2 pr-3 text-right">Level</th>
                        <th class="py-2 pr-3 text-right">XP</th>
                        <th class="py-2 pr-3 text-right">Gesammelt</th>
                        <th class="py-2 text-right">Shiny</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dex-border/40">
                    @forelse ($rangliste as $index => $trainer)
                        <tr @class(['bg-dex-soft/30' => $trainer->id === auth()->id()])>
                            <td class="py-2 pr-3 font-dex text-base">
                                {{ $index + 1 }}
                                @if ($index === 0) 🥇 @elseif ($index === 1) 🥈 @elseif ($index === 2) 🥉 @endif
                            </td>
                            <td class="py-2 pr-3">
                                {{ $trainer->name }}
                                @if ($trainer->id === auth()->id())
                                    <span class="text-[10px] text-dex-accent">(Du)</span>
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-right font-dex">{{ $trainer->level() }}</td>
                            <td class="py-2 pr-3 text-right font-dex">
                                {{ number_format($trainer->xp, 0, ',', '.') }}
                            </td>
                            <td class="py-2 pr-3 text-right font-dex">{{ $trainer->gesammelt }}</td>
                            <td class="py-2 text-right font-dex">{{ $trainer->shinys }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-dex-muted">
                                {{ $nurFreunde
                                    ? 'Noch keine Freunde hinzugefügt.'
                                    : 'Noch keine anderen Trainer registriert.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ── Freundesliste (spec.md 2.10) ────────────────────────────────── --}}
        <aside class="space-y-4">
            <div class="pixel-panel p-5">
                <h2 class="font-pixel text-xs uppercase text-dex-accent">Freund hinzufügen</h2>

                <form method="POST" action="{{ route('friends.add') }}" class="mt-3">
                    @csrf
                    <label for="email" class="mb-1 block text-xs uppercase text-dex-muted">E-Mail</label>
                    <input type="email" id="email" name="email" class="pixel-input" required
                           placeholder="trainer@example.com">
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    <button type="submit" class="pixel-button mt-3">Anfrage senden</button>
                </form>
            </div>

            @if ($anfragen->isNotEmpty())
                <div class="pixel-panel border-dex-accent p-5">
                    <h2 class="font-pixel text-xs uppercase text-dex-accent">Offene Anfragen</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($anfragen as $anfrage)
                            <li class="flex items-center justify-between gap-2">
                                <span>{{ $anfrage->user->name }}</span>
                                <form method="POST" action="{{ route('friends.accept', $anfrage) }}">
                                    @csrf
                                    <button type="submit" class="pixel-button-ghost">Annehmen</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="pixel-panel p-5">
                <h2 class="font-pixel text-xs uppercase text-dex-accent">
                    Deine Freunde ({{ $freunde->count() }})
                </h2>

                @if ($freunde->isEmpty())
                    <p class="mt-3 text-sm text-dex-muted">Noch niemand in der Liste.</p>
                @else
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach ($freunde as $freund)
                            <li class="flex items-center justify-between gap-2 border-b border-dex-border/30 pb-1">
                                <span>{{ $freund->name }}</span>
                                <span class="font-dex text-dex-muted">LV {{ $freund->level() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </aside>
    </div>
</x-app-layout>
