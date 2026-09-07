<button {{ $attributes->merge(['type' => 'button', 'class' => 'pixel-button-ghost disabled:opacity-25']) }}>
    {{ $slot }}
</button>
