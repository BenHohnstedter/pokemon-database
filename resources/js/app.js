import './bootstrap';

import Alpine from 'alpinejs';
import { music, sfx } from './retro-audio';

window.Alpine = Alpine;

/**
 * Klick-Toggle für Besitz, Shiny und Wunschliste (spec.md 2.5).
 *
 * Optimistisches Update: die Karte reagiert sofort, der Request läuft nebenbei.
 * Schlägt er fehl, wird der Zustand zurückgedreht – bei 1.300 Karten wäre
 * alles andere zäh.
 */
function dexToggle(config) {
    return {
        owned: config.owned,
        shiny: config.shiny,
        favourite: config.favourite,
        busy: false,
        pop: false,

        async umschalten(variante = 'normal') {
            if (this.busy) {
                return;
            }

            const vorher = { owned: this.owned, shiny: this.shiny, favourite: this.favourite };

            if (variante === 'shiny') {

                this.shiny = !this.shiny;
            } else if (variante === 'favorit') {
                this.favourite = !this.favourite;
            } else {
                this.owned = !this.owned;
            }

            this.busy = true;
            this.pop = true;
            window.setTimeout(() => (this.pop = false), 340);

            this.spieleSound(variante);

            try {
                const response = await fetch(config.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': config.token,
                    },
                    body: JSON.stringify({ variante }),
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();
                window.dispatchEvent(new CustomEvent('dex:xp', { detail: data }));

                // Neuer Orden: Konfetti und Fanfare (spec.md 2.9).
                if (Array.isArray(data.orden) && data.orden.length > 0) {
                    feiereMeilenstein();

                    if (config.sound) {
                        sfx.milestone();
                    }

                    window.dispatchEvent(new CustomEvent('dex:orden', { detail: data.orden }));
                }
            } catch (error) {
                // Zurückdrehen, damit die Anzeige nicht lügt.
                this.owned = vorher.owned;
                this.shiny = vorher.shiny;
                this.favourite = vorher.favourite;
                window.dispatchEvent(new CustomEvent('dex:fehler', {
                    detail: 'Konnte nicht gespeichert werden – bitte Seite neu laden.',
                }));
            } finally {
                this.busy = false;
            }
        },

        spieleSound(variante) {
            if (!config.sound) {
                return;
            }

            if (variante === 'shiny') {
                this.shiny ? sfx.shiny() : sfx.release();

                return;
            }

            if (variante === 'normal') {
                this.owned ? sfx.catch() : sfx.release();
            }
        },
    };
}

/** Audio-Einstellungen des Nutzers, global im Layout eingehängt. */
function dexAudio(config) {
    return {
        musicOn: config.music,
        volume: config.volume,

        init() {
            // Autoplay ist gesperrt, bis der Nutzer irgendwo geklickt hat.
            if (this.musicOn) {
                document.addEventListener('click', () => this.starteMusik(), { once: true });
            }
        },

        starteMusik() {
            if (this.musicOn) {
                music.setVolume(this.volume);
                music.start(this.volume / 100);
            }
        },

        umschalten() {
            this.musicOn = !this.musicOn;
            this.musicOn ? this.starteMusik() : music.stop();
        },
    };
}

/** Pixel-Konfetti bei Meilensteinen (spec.md 2.9). */
function feiereMeilenstein(anzahl = 28) {
    if (document.documentElement.dataset.reduceMotion === 'true') {
        return;
    }

    const farben = ['#facc15', '#34d399', '#60a5fa', '#f472b6', '#fb923c'];
    const container = document.createElement('div');
    container.className = 'pointer-events-none fixed inset-x-0 top-0 z-50 flex justify-center';

    for (let i = 0; i < anzahl; i++) {
        const pixel = document.createElement('span');
        pixel.className = 'absolute block h-2 w-2 animate-confetti-fall';
        pixel.style.backgroundColor = farben[i % farben.length];
        pixel.style.left = `${Math.random() * 100}%`;
        pixel.style.animationDelay = `${Math.random() * 0.5}s`;
        container.appendChild(pixel);
    }

    document.body.appendChild(container);
    window.setTimeout(() => container.remove(), 2200);
}

window.dexToggle = dexToggle;
window.dexAudio = dexAudio;
window.feiereMeilenstein = feiereMeilenstein;
window.dexSfx = sfx;

Alpine.start();

/*
| Service Worker für die Installierbarkeit auf dem Handy (spec.md 6, 7).
| Registrierung erst nach dem Laden, damit sie den ersten Seitenaufbau nicht
| ausbremst. Über HTTP nur auf localhost erlaubt – deshalb der Origin-Check,
| sonst wirft XAMPP unter http://localhost/... eine Konsolenwarnung.
*/
if ('serviceWorker' in navigator && (window.isSecureContext || location.hostname === 'localhost')) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Ohne Service Worker läuft die App normal weiter, nur eben nicht offline.
        });
    });
}
