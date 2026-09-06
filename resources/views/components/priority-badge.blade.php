@props([
    'priority',
    'showLabel' => true,
])

{{-- Dringlichkeitsstufe aus spec.md 2.7 --}}
<span {{ $attributes->merge([
        'class' => 'inline-flex items-center gap-1 border px-2 py-0.5 text-[10px] uppercase tracking-wide '.$priority->badgeClasses(),
    ]) }}
      title="{{ $priority->description() }}">
    <span aria-hidden="true">{{ $priority->icon() }}</span>
    @if ($showLabel)
        <span>{{ $priority->label() }}</span>
    @else
        <span class="sr-only">{{ $priority->label() }}</span>
    @endif
</span>
