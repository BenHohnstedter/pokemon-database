@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-sm font-medium text-dex-success']) }}>
        {{ $status }}
    </div>
@endif
