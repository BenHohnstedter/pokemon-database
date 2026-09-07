@props(['disabled' => false])

{{-- Dunkel wie der Rest der App: .pixel-input holt sich die Theme-Farben. --}}
<input @disabled($disabled) {{ $attributes->merge(['class' => 'pixel-input']) }}>
