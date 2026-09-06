<x-app-layout>
    <x-slot name="title">Spiele – {{ config('app.name') }}</x-slot>

    <x-slot name="header">
        <h1 class="font-pixel text-sm text-dex-accent sm:text-base">Spiel für Spiel abarbeiten</h1>
        <p class="mt-2 text-sm text-dex-muted">
            Such Dir das Spiel aus, das gerade in der Konsole steckt – Du bekommst die
            Liste aller Pokémon, die Dir daraus noch fehlen.
        </p>
    </x-slot>

    @foreach ($spiele as $generation => $titel)
        <section class="pixel-panel mb-5 p-5">
            <h2 class="font-pixel text-xs uppercase text-dex-accent">
                {{ $generation === 0 ? 'Nebenreihe' : "Generation {$generation}" }}
            </h2>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($titel as $spiel)
                    @php
                        $offen = $offeneJeSpiel[$spiel->id] ?? 0;
                        $besitzt = in_array($spiel->id, $besesseneSpiele, true);
                    @endphp

                    <a href="{{ route('games.show', $spiel) }}"
                       @class([
                           'pixel-panel-soft block p-3 transition-colors hover:border-dex-accent',
                           'border-dex-success/70' => $besitzt,
                       ])>
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <span class="block truncate font-semibold">{{ $spiel->name_de }}</span>
                                <span class="block text-[11px] text-dex-muted">
                                    {{ $spiel->platform->label() }}
                                </span>
                            </div>

                            @if ($besitzt)
                                <span class="shrink-0 text-[10px] uppercase text-dex-success"
                                      title="Du besitzt dieses Spiel">✔ Deins</span>
                            @endif
                        </div>

                        <p class="mt-2 text-xs">
                            @if ($offen > 0)
                                <span class="font-dex text-base text-dex-accent">{{ $offen }}</span>
                                <span class="text-dex-muted">
                                    {{ $offen === 1 ? 'Pokémon fehlt Dir noch' : 'Pokémon fehlen Dir noch' }}
                                </span>
                            @else
                                <span class="text-dex-success">Hier fehlt Dir nichts mehr 🎉</span>
                            @endif
                        </p>

                        <p class="mt-1 text-[10px] text-dex-muted">
                            @if ($spiel->home_compatible && ! $spiel->bank_only)
                                direkt an HOME
                            @elseif ($spiel->bank_only)
                                <span class="text-dex-danger">Transfer über Pokémon Bank</span>
                            @else
                                kein Transferweg nach HOME
                            @endif
                        </p>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</x-app-layout>
