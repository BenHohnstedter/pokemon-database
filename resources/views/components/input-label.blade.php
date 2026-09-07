@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-xs font-semibold uppercase tracking-wide text-dex-muted']) }}>
    {{ $value ?? $slot }}
</label>
