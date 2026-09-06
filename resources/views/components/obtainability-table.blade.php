@props(['quellen'])

{{-- Bezugsquellen als Tabelle (spec.md 2.3). --}}
<div {{ $attributes->merge(['class' => 'pixel-scroll-x']) }}>
    <table class="w-full min-w-[36rem] text-left text-sm">
        <thead class="border-b-2 border-dex-border text-[10px] uppercase text-dex-muted">
            <tr>
                <th class="py-2 pr-3">Spiel</th>
                <th class="py-2 pr-3">Methode</th>
                <th class="py-2 pr-3">Ort / Detail</th>
                <th class="py-2">Weg nach HOME</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-dex-border/40">
            @foreach ($quellen as $quelle)
                <tr @class(['opacity-50' => $quelle->event_expired])>
                    <td class="py-2 pr-3">
                        {{-- Die Spielansicht liegt hinter dem Login – für Gäste
                             wäre der Link eine Sackgasse. --}}
                        @auth
                            <a href="{{ route('games.show', $quelle->game) }}"
                               class="font-semibold underline decoration-dotted hover:text-dex-accent"
                               title="Alles ansehen, was Dir in diesem Spiel noch fehlt">
                                {{ $quelle->game->name_de }}
                            </a>
                        @else
                            <span class="font-semibold">{{ $quelle->game->name_de }}</span>
                        @endauth
                        <span class="block text-[10px] text-dex-muted">
                            {{ $quelle->game->platform->label() }}
                        </span>
                    </td>
                    <td class="py-2 pr-3">
                        {{ $quelle->method->label() }}
                        @if ($quelle->event_expired)
                            <span class="block text-[10px] text-dex-danger">Event vorbei</span>
                        @endif
                    </td>
                    <td class="py-2 pr-3 text-dex-muted">{{ $quelle->location_detail ?: '—' }}</td>
                    <td class="py-2 text-[11px]">
                        @if ($quelle->game->home_compatible && ! $quelle->game->bank_only)
                            <span class="text-dex-success">direkt an HOME</span>
                        @elseif ($quelle->game->bank_only)
                            <span class="text-dex-danger">über Pokémon Bank</span>
                        @else
                            <span class="text-dex-muted">kein Transferweg</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
