/**
 * 8-Bit-Sounds und Chiptune-Loop per Web Audio API (spec.md 2.9).
 *
 * Bewusst synthetisiert statt aus Sounddateien: so liegen keine fremden
 * Audio-Assets im öffentlichen Repo, und der Fang-Jingle kostet keinen
 * einzigen Request.
 *
 * Der AudioContext wird erst beim ersten echten Nutzerklick erzeugt – Browser
 * blockieren Autoplay sonst ohnehin.
 */

let context = null;

function ensureContext() {
    if (context === null) {
        const Ctor = window.AudioContext || window.webkitAudioContext;

        if (!Ctor) {
            return null;
        }

        context = new Ctor();
    }

    if (context.state === 'suspended') {
        context.resume();
    }

    return context;
}

/** Ein einzelner Rechteckton – die Grundform des NES-Klangs. */
function blip(frequency, startAt, duration, volume = 0.08, type = 'square') {
    const ctx = ensureContext();

    if (!ctx) {
        return;
    }

    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();

    oscillator.type = type;
    oscillator.frequency.setValueAtTime(frequency, ctx.currentTime + startAt);

    // Harte Hüllkurve statt weichem Fade – klingt nach Hardware, nicht nach Synth.
    gain.gain.setValueAtTime(0.0001, ctx.currentTime + startAt);
    gain.gain.exponentialRampToValueAtTime(volume, ctx.currentTime + startAt + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + startAt + duration);

    oscillator.connect(gain).connect(ctx.destination);
    oscillator.start(ctx.currentTime + startAt);
    oscillator.stop(ctx.currentTime + startAt + duration + 0.02);
}

export const sfx = {
    /** Aufsteigender Dreiklang beim neuen Eintrag. */
    catch() {
        blip(523.25, 0, 0.09);
        blip(659.25, 0.08, 0.09);
        blip(783.99, 0.16, 0.16);
    },

    /** Kurzes Abwärts-Blip beim Zurücknehmen. */
    release() {
        blip(392.0, 0, 0.07, 0.05);
        blip(261.63, 0.06, 0.1, 0.05);
    },

    /** Glitzernd und höher – für Shiny. */
    shiny() {
        blip(880.0, 0, 0.07, 0.07, 'triangle');
        blip(1174.66, 0.06, 0.07, 0.07, 'triangle');
        blip(1567.98, 0.12, 0.18, 0.07, 'triangle');
    },

    /** Fanfare beim Meilenstein/Achievement. */
    milestone() {
        [523.25, 659.25, 783.99, 1046.5].forEach((f, i) => blip(f, i * 0.1, 0.14, 0.09));
        blip(1046.5, 0.5, 0.3, 0.09, 'triangle');
    },
};

/**
 * Hintergrundmusik: ruhige, frei lizenzierte Stücke zum Durchschalten.
 *
 * Früher stand hier ein aus Oszillatoren zusammengesetzter Chiptune-Loop. Der
 * klang nach vier Takten wie vier Takte -- zum Danebenlaufen taugt er nicht.
 * Jetzt spielt ein ganz gewöhnliches <audio>-Element die Stücke aus
 * config/pokedex.php (`music`), Herkunft und Lizenz stehen in
 * public/audio/HERKUNFT.md.
 *
 * Standardmäßig aus (spec.md 2.9) und über die Nutzereinstellungen schaltbar.
 * Autoplay ist bis zum ersten Klick gesperrt -- darum kümmert sich dexAudio.
 */
export const music = {
    /** @type {{datei: string, titel: string, urheber: string}[]} */
    tracks: [],
    index: 0,
    element: null,
    volume: 0.35,

    setTracks(tracks) {
        this.tracks = Array.isArray(tracks) ? tracks : [];
    },

    aktuell() {
        return this.tracks[this.index] ?? null;
    },

    titel() {
        const stueck = this.aktuell();

        return stueck ? `${stueck.titel} — ${stueck.urheber}` : 'Keine Musik hinterlegt';
    },

    start(volume = 0.35) {
        this.volume = volume;

        const stueck = this.aktuell();

        if (! stueck) {
            return;
        }

        if (this.element === null) {
            this.element = new Audio();
            // Ein Stück läuft in Schleife, bis jemand weiterschaltet.
            this.element.loop = true;
            this.element.preload = 'none';
        }

        const quelle = stueck.datei;

        // Nur neu laden, wenn wirklich ein anderes Stück dran ist -- sonst
        // springt das laufende beim Lautstärkeregeln an den Anfang zurück.
        if (! this.element.src.endsWith(quelle)) {
            this.element.src = quelle;
        }

        this.element.volume = this.volume;

        // play() liefert ein Promise, das der Browser ablehnt, solange keine
        // Nutzergeste vorliegt. Das ist kein Fehler, den jemand sehen müsste.
        const versuch = this.element.play();

        if (versuch && typeof versuch.catch === 'function') {
            versuch.catch(() => {});
        }
    },

    /** Nächstes Stück; springt am Ende der Liste wieder auf das erste. */
    next() {
        if (this.tracks.length === 0) {
            return null;
        }

        this.index = (this.index + 1) % this.tracks.length;

        if (this.element !== null && ! this.element.paused) {
            this.start(this.volume);
        }

        return this.aktuell();
    },

    stop() {
        if (this.element !== null) {
            this.element.pause();
        }
    },

    laeuft() {
        return this.element !== null && ! this.element.paused;
    },

    setVolume(value) {
        this.volume = Math.min(1, Math.max(0, value / 100));

        if (this.element !== null) {
            this.element.volume = this.volume;
        }
    },
};
