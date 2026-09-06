/**
 * Service Worker fuer die PWA-Installierbarkeit (spec.md 6, 7).
 *
 * Bewusst schlank gehalten: Nur die Huelle wird gecacht, damit die App auch
 * offline startet. Sammlungsdaten laufen immer ueber das Netz - ein veralteter
 * Besitzstand waere schlimmer als eine Fehlermeldung.
 */

const CACHE = 'dex-rescue-v1';
const SHELL = ['./', './manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Alles ausser GET (Besitz-Toggles, Formulare) nie abfangen.
    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;
    }

    // Sprites und Assets: erst Cache, dann Netz.
    if (/\.(?:png|jpg|jpeg|svg|webp|woff2?|css|js)$/.test(new URL(request.url).pathname)) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(CACHE).then((cache) => cache.put(request, copy));

                return response;
            }))
        );

        return;
    }

    // HTML-Seiten: erst Netz, Cache nur als Notfall.
    event.respondWith(
        fetch(request).catch(() => caches.match(request).then((hit) => hit || caches.match('./')))
    );
});
