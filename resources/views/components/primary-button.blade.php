<button {{ $attributes->merge(['type' => 'submit', 'class' => 'pixel-button']) }}>
    {{ $slot }}
</button>
