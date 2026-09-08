import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    /*
    | Bewusst OHNE './storage/framework/views/*.php': Das ist der kompilierte
    | Blade-Cache und damit maschinenlokal. Wer die App vorher im Browser
    | benutzt hat, baut ein anderes CSS als ein frischer Checkout -- hier
    | zuletzt 51,8 kB gegenüber 39,1 kB auf dem CI-Runner. Alle Klassen stehen
    | ohnehin in den Blade-Quellen darunter; der Cache fügt nichts hinzu,
    | was hier fehlt, macht den Build aber unreproduzierbar.
    */
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                pixel: ['"Press Start 2P"', ...defaultTheme.fontFamily.mono],
                dex: ['VT323', ...defaultTheme.fontFamily.mono],
            },

            // Die Themes aus spec.md 5 laufen über CSS-Variablen, damit ein
            // Wechsel nur das data-theme-Attribut am <html> umsetzen muss.
            colors: {
                dex: {
                    bg: 'rgb(var(--dex-bg) / <alpha-value>)',
                    panel: 'rgb(var(--dex-panel) / <alpha-value>)',
                    soft: 'rgb(var(--dex-panel-soft) / <alpha-value>)',
                    border: 'rgb(var(--dex-border) / <alpha-value>)',
                    text: 'rgb(var(--dex-text) / <alpha-value>)',
                    muted: 'rgb(var(--dex-muted) / <alpha-value>)',
                    accent: 'rgb(var(--dex-accent) / <alpha-value>)',
                    danger: 'rgb(var(--dex-danger) / <alpha-value>)',
                    success: 'rgb(var(--dex-success) / <alpha-value>)',
                },
            },

            keyframes: {
                'pixel-pop': {
                    '0%': { transform: 'scale(1)' },
                    '40%': { transform: 'scale(1.18)' },
                    '100%': { transform: 'scale(1)' },
                },
                'confetti-fall': {
                    '0%': { transform: 'translateY(-20px) rotate(0deg)', opacity: '1' },
                    '100%': { transform: 'translateY(120px) rotate(220deg)', opacity: '0' },
                },
            },

            animation: {
                'pixel-pop': 'pixel-pop 320ms steps(6, end)',
                'confetti-fall': 'confetti-fall 1.4s ease-in forwards',
            },
        },
    },

    plugins: [forms],
};
