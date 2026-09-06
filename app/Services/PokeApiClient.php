<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Dünner HTTP-Client für die PokéAPI (spec.md 4).
 *
 * Zwei Dinge sind hier wichtig:
 *
 * 1. **Plattencache.** Ein voller Import macht mehrere tausend Requests. Jede
 *    Antwort landet als JSON-Datei unter `storage/app/pokeapi-cache`, damit ein
 *    erneuter Lauf (neue Generation, korrigierte Zuordnung) nicht wieder die
 *    komplette API abklappert. Die PokéAPI bittet ausdrücklich um Caching.
 * 2. **Wiederholungen.** Einzelne 5xx/Timeouts sollen den Import nicht abbrechen.
 */
class PokeApiClient
{
    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?string $cacheDir = null,
    ) {}

    /** Voll qualifizierte URL oder Pfad relativ zur API-Basis. */
    public function get(string $path): array
    {
        $url = str_starts_with($path, 'http')
            ? $path
            : $this->baseUrl().'/'.ltrim($path, '/');

        if ($cached = $this->readCache($url)) {
            return $cached;
        }

        $response = Http::timeout(config('pokedex.pokeapi.timeout', 30))
            ->retry(
                config('pokedex.pokeapi.retries', 3),
                config('pokedex.pokeapi.retry_delay_ms', 500),
                // Nur Verbindungsfehler und 5xx wiederholen. Ein 404 heißt, dass
                // es die Ressource nicht gibt – dreimal nachzufragen kostet beim
                // Vollimport spürbar Zeit und ändert nichts.
                when: fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            )
            ->acceptJson()
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException("PokéAPI-Abruf fehlgeschlagen ({$response->status()}): {$url}");
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException("Unerwartete Antwort von der PokéAPI: {$url}");
        }

        $this->writeCache($url, $data);

        return $data;
    }

    /** Existiert die Ressource? Für Arten, deren ID über dem aktuellen Dex liegt. */
    public function exists(string $path): bool
    {
        try {
            $this->get($path);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Deutscher Name aus einem PokéAPI-`names`-Array, mit Fallback auf Englisch.
     *
     * @param  array<int,array{name:string,language:array{name:string}}>  $names
     */
    public static function localizedName(array $names, string $language = 'de', string $fallback = ''): string
    {
        foreach ($names as $entry) {
            if (($entry['language']['name'] ?? null) === $language) {
                return $entry['name'];
            }
        }

        if ($language !== 'en') {
            return self::localizedName($names, 'en', $fallback);
        }

        return $fallback;
    }

    /** Letztes Pfadsegment einer PokéAPI-URL, also die ID. */
    public static function idFromUrl(?string $url): ?int
    {
        if (! $url) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $url)));
        $last = end($segments);

        return is_numeric($last) ? (int) $last : null;
    }

    public function clearCache(): int
    {
        $dir = $this->cacheDirectory();

        if (! is_dir($dir)) {
            return 0;
        }

        $files = glob($dir.'/*.json') ?: [];

        foreach ($files as $file) {
            @unlink($file);
        }

        return count($files);
    }

    private function baseUrl(): string
    {
        return rtrim($this->baseUrl ?? config('pokedex.pokeapi.base_url'), '/');
    }

    private function cacheDirectory(): string
    {
        return $this->cacheDir ?? config('pokedex.pokeapi.cache_dir');
    }

    private function cachePath(string $url): string
    {
        return $this->cacheDirectory().'/'.sha1($url).'.json';
    }

    private function readCache(string $url): ?array
    {
        if (! config('pokedex.pokeapi.cache_enabled', true)) {
            return null;
        }

        $path = $this->cachePath($url);

        if (! is_file($path)) {
            return null;
        }

        $ttlDays = (int) config('pokedex.pokeapi.cache_ttl_days', 30);

        if ($ttlDays > 0 && filemtime($path) < now()->subDays($ttlDays)->getTimestamp()) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    private function writeCache(string $url, array $data): void
    {
        if (! config('pokedex.pokeapi.cache_enabled', true)) {
            return;
        }

        $dir = $this->cacheDirectory();

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return; // Cache ist eine Optimierung, kein Muss – Import läuft weiter.
        }

        @file_put_contents($this->cachePath($url), json_encode($data));
    }
}
