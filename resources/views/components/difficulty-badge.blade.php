@props(['difficulty'])

<span {{ $attributes->merge([
    'class' => 'inline-block border px-2 py-0.5 text-[10px] uppercase tracking-wide '.$difficulty->badgeClasses(),
]) }}>
    {{ $difficulty->label() }}
</span>
