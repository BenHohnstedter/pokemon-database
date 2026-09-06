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
 * Dezenter Hintergrund-Loop. Standardmäßig aus (spec.md 2.9) und über die
 * Nutzereinstellungen schaltbar.
 */
export const music = {
    timer: null,
    gain: null,
    volume: 0.35,

    // Eine schlichte Vier-Takt-Basslinie plus Melodie – bewusst unaufdringlich.
    bass: [130.81, 130.81, 174.61, 196.0],
    lead: [523.25, 659.25, 587.33, 493.88, 523.25, 783.99, 659.25, 587.33],

    start(volume = 0.35) {
        const ctx = ensureContext();

        if (!ctx || this.timer !== null) {
            return;
        }

        this.volume = volume;
        let step = 0;

        const tick = () => {
            const bassNote = this.bass[step % this.bass.length];
            const leadNote = this.lead[step % this.lead.length];

            blip(bassNote, 0, 0.38, 0.05 * this.volume, 'triangle');
            blip(leadNote, 0.02, 0.22, 0.035 * this.volume);

            step += 1;
        };

        tick();
        this.timer = window.setInterval(tick, 420);
    },

    stop() {
        if (this.timer !== null) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    },

    setVolume(value) {
        this.volume = Math.min(1, Math.max(0, value / 100));
    },
};
