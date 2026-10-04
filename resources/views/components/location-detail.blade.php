@props(['quelle'])

@php
    // Nur PokeAPI-Quellen tragen strukturierte Orte (Slug + deutscher Name).
    // CSV-, kuratierte und Fallback-Zeilen zeigen weiterhin den reinen Text.
    $orte = $quelle->locationAreas();
@endphp

@if (count($orte) > 0)
    @foreach ($orte as $i => $ort)
        @if ($i > 0), @endif
        <a href="{{ $quelle->locationWikiUrl($ort['name_de']) }}"
           target="_blank"
           rel="noopener noreferrer"
           title="Welche Pokémon es hier gibt – im PokéWiki nachschlagen"
           class="text-dex-text underline decoration-dotted hover:text-dex-accent">{{ $ort['name_de'] }}</a>
    @endforeach
    @if ($quelle->locationsTruncated()) u.a. @endif
@else
    {{ $quelle->location_detail ?: '—' }}
@endif
