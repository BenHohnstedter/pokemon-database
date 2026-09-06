@php
    $links = [
        ['route' => 'dashboard', 'label' => 'Dashboard', 'auth' => true],
        ['route' => 'pokedex.index', 'label' => 'Pokédex', 'auth' => false],
        ['route' => 'collection.bulk', 'label' => 'Masseneingabe', 'auth' => true],
        ['route' => 'statistics', 'label' => 'Statistik', 'auth' => true],
        ['route' => 'collection.transfer', 'label' => 'Sichern', 'auth' => true],
        ['route' => 'trainer.card', 'label' => 'Trainerkarte', 'auth' => true],
        ['route' => 'trainer.leaderboard', 'label' => 'Bestenliste', 'auth' => true],
    ];
@endphp

<nav x-data="{ offen: false }" class="border-b-4 border-dex-border bg-dex-panel">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-2">

            <div class="flex min-w-0 items-center gap-6">
                <a href="{{ auth()->check() ? route('dashboard') : route('home') }}"
                   class="font-pixel text-xs text-dex-accent sm:text-sm">
                    DEX&#8209;RESCUE
                </a>

                <div class="hidden items-center gap-1 lg:flex">
                    @foreach ($links as $link)
                        @if (! $link['auth'] || auth()->check())
                            <a href="{{ route($link['route']) }}"
                               @class([
                                   'px-3 py-2 text-sm transition-colors',
                                   'text-dex-accent border-b-2 border-dex-accent' => request()->routeIs($link['route']),
                                   'text-dex-muted hover:text-dex-text' => ! request()->routeIs($link['route']),
                               ])>
                                {{ $link['label'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="flex min-w-0 shrink-0 items-center gap-3">
                @auth
                    {{-- Chiptune-Schalter, standardmäßig aus (spec.md 2.9).
                         Auf sehr schmalen Displays weggelassen, sonst schiebt die
                         Kopfzeile die ganze Seite in den Querlauf. --}}
                    <button type="button"
                            x-on:click="umschalten()"
                            class="pixel-button-ghost hidden sm:inline-flex"
                            :aria-pressed="musicOn ? 'true' : 'false'"
                            :title="musicOn ? 'Musik ausschalten' : 'Musik einschalten'">
                        <span x-text="musicOn ? '♪ an' : '♪ aus'"></span>
                    </button>

                    <div class="hidden text-right text-xs lg:block">
                        <div class="font-pixel text-[10px] text-dex-accent">
                            LV {{ auth()->user()->level() }}
                        </div>
                        <div class="text-dex-muted">{{ number_format(auth()->user()->xp, 0, ',', '.') }} XP</div>
                    </div>

                    <div x-data="{ auf: false }" class="relative">
                        <button type="button" x-on:click="auf = ! auf" dusk="user-menu"
                                class="pixel-button-ghost max-w-[9rem] truncate">
                            {{ Str::limit(auth()->user()->name, 14) }} ▾
                        </button>

                        <div x-show="auf"
                             x-on:click.outside="auf = false"
                             x-cloak
                             class="pixel-panel absolute right-0 z-40 mt-1 w-52 py-1 text-sm">
                            <a href="{{ route('settings.edit') }}" class="block px-4 py-2 hover:bg-dex-soft/50">
                                Einstellungen
                            </a>
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2 hover:bg-dex-soft/50">
                                Profil
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-left hover:bg-dex-soft/50">
                                    Abmelden
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="pixel-button-ghost">Anmelden</a>
                    <a href="{{ route('register') }}" class="pixel-button">Registrieren</a>
                @endauth

                <button type="button"
                        x-on:click="offen = ! offen"
                        class="pixel-button-ghost lg:hidden"
                        aria-label="Menü umschalten">
                    ☰
                </button>
            </div>
        </div>
    </div>

    <div x-show="offen" x-cloak class="border-t-2 border-dex-border lg:hidden">
        @foreach ($links as $link)
            @if (! $link['auth'] || auth()->check())
                <a href="{{ route($link['route']) }}"
                   class="block px-4 py-3 text-sm hover:bg-dex-soft/40">
                    {{ $link['label'] }}
                </a>
            @endif
        @endforeach
    </div>
</nav>
