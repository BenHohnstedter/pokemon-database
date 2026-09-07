<button {{ $attributes->merge(['type' => 'submit', 'class' => 'pixel-button-danger']) }}>
    {{ $slot }}
</button>
