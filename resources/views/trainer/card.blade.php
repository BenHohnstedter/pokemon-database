<x-app-layout>
    <x-slot name="title">Trainerkarte – {{ config('app.name') }}</x-slot>

    <x-slot name="header">
        <h1 class="font-pixel text-sm text-dex-accent sm:text-base">Trainerkarte</h1>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- ── Die Karte selbst, im GameBoy-Stil (spec.md 2.9) ──────────────── --}}
        <section class="pixel-panel scanlines relative overflow-hidden p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-pixel text-[10px] uppercase text-dex-muted">Trainer</p>
                    <p class="mt-1 font-pixel text-sm">{{ $user->name }}</p>
                </div>
                <div class="text-right">
                    <p class="font-pixel text-[10px] uppercase text-dex-muted">Level</p>
                    <p class="font-dex text-3xl text-dex-accent">{{ $user->level() }}</p>
                </div>
            </div>

            <div class="mt-4">
                <div class="mb-1 flex justify-between text-[10px] uppercase text-dex-muted">
                    <span>XP</span>
                    <span class="font-dex text-sm text-dex-text">
                        {{ number_format($user->xp, 0, ',', '.') }}
                    </span>
                </div>
                <div class="dex-progress">
                    <span style="width: {{ $user->levelProgressPercent() }}%"></span>
                </div>
                <p class="mt-1 text-[10px] text-dex-muted">
                    Noch {{ number_format($user->xpToNextLevel(), 0, ',', '.') }} XP bis Level
                    {{ $user->level() + 1 }}
                </p>
            </div>

            <dl class="mt-5 grid grid-cols-3 gap-2 text-center">
                <div class="pixel-panel-soft p-2">
                    <dt class="text-[9px] uppercase text-dex-muted">Dex</dt>
                    <dd class="font-dex text-xl">{{ $basis->owned }}</dd>
                </div>
                <div class="pixel-panel-soft p-2">
                    <dt class="text-[9px] uppercase text-dex-muted">Formen</dt>
                    <dd class="font-dex text-xl">{{ $regional->owned }}</dd>
                </div>
                <div class="pixel-panel-soft p-2">
                    <dt class="text-[9px] uppercase text-dex-muted">Shiny</dt>
                    <dd class="font-dex text-xl">{{ $shiny->owned }}</dd>
                </div>
            </dl>

            @if ($user->login_streak > 0)
                <p class="mt-4 text-center text-xs text-dex-accent">
                    🔥 {{ $user->login_streak }} Tage Streak
                </p>
            @endif

            <p class="mt-4 text-center text-[10px] text-dex-muted">
                Dabei seit {{ $user->created_at->format('m/Y') }}
            </p>
        </section>

        {{-- ── Fortschrittsringe je Region ─────────────────────────────────── --}}
        <section class="pixel-panel p-5">
            <h2 class="mb-4 font-pixel text-xs uppercase text-dex-accent">Regionen</h2>
            <div class="space-y-4">
                @forelse ($generationen as $bar)
                    <x-progress-bar :bar="$bar" compact />
                @empty
                    <p class="text-sm text-dex-muted">Noch keine Daten importiert.</p>
                @endforelse
            </div>
        </section>

        {{-- ── Badges (spec.md 2.9) ────────────────────────────────────────── --}}
        <section class="pixel-panel p-5">
            <h2 class="mb-4 font-pixel text-xs uppercase text-dex-accent">
                Orden ({{ $freigeschaltet->count() }}/{{ $freigeschaltet->count() + $offen->count() }})
            </h2>

            @if ($freigeschaltet->isEmpty())
                <p class="text-sm text-dex-muted">
                    Noch kein Orden freigeschaltet – markiere Dein erstes Pokémon als besessen.
                </p>
            @else
                <ul class="grid grid-cols-2 gap-2">
                    @foreach ($freigeschaltet as $achievement)
                        <li class="pixel-panel-soft border-dex-accent/60 p-2 text-center"
                            title="{{ $achievement->description }}">
                            <span class="block text-xl" aria-hidden="true">{{ $achievement->icon }}</span>
                            <span class="mt-1 block text-[10px] leading-tight">{{ $achievement->name }}</span>
                            <span class="mt-1 block text-[9px] text-dex-muted">
                                {{ $achievement->pivot->unlocked_at
                                    ? \Illuminate\Support\Carbon::parse($achievement->pivot->unlocked_at)->format('d.m.Y')
                                    : '' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($offen->isNotEmpty())
                <h3 class="mb-2 mt-5 font-pixel text-[10px] uppercase text-dex-muted">Noch offen</h3>
                <ul class="grid grid-cols-2 gap-2">
                    @foreach ($offen as $achievement)
                        <li class="pixel-panel-soft p-2 text-center opacity-50"
                            title="{{ $achievement->description }}">
                            <span class="block text-xl grayscale" aria-hidden="true">{{ $achievement->icon }}</span>
                            <span class="mt-1 block text-[10px] leading-tight">{{ $achievement->name }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-app-layout>
