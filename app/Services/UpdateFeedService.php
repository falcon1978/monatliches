<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UpdateFeedService
{
    public function getCachedInfo(int $ttlSeconds = 21600): ?array
    {
        $cacheKey = 'update.feed.info';

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && ! empty($cached['current'])) {
                $current = $this->currentVersion();
                if ((string) $cached['current'] === $current) {
                    return $cached;
                }
            } elseif (! is_array($cached)) {
                return $cached;
            }
            Cache::forget($cacheKey);
        }

        try {
            $info = $this->fetchInfo();
        } catch (\Throwable) {
            return null;
        }

        Cache::put($cacheKey, $info, $ttlSeconds);
        return $info;
    }

    public function fetchInfo(): array
    {
        $feedUrl = config('update.feed_url');
        if (! $feedUrl) {
            throw new RuntimeException('Update-Feed ist nicht konfiguriert.');
        }

        $response = Http::timeout(10)->get($feedUrl);
        if (! $response->successful()) {
            throw new RuntimeException('Update-Feed konnte nicht geladen werden.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Update-Feed ist ungültig.');
        }

        $current = $this->currentVersion();
        $latest = (string) ($payload['version'] ?? '');
        if ($latest === '') {
            throw new RuntimeException('Update-Feed enthält keine Version.');
        }

        $updateAvailable = version_compare($current, $latest, '<');

        return [
            'current' => $current,
            'latest' => $latest,
            'update_available' => $updateAvailable,
            'download_url' => $payload['download_url'] ?? null,
            'sha256' => $payload['sha256'] ?? null,
            'changelog' => $payload['changelog'] ?? null,
            'released_at' => $payload['released_at'] ?? null,
        ];
    }

    public function currentVersion(): string
    {
        $installedVersion = null;
        try {
            if (Storage::disk('local')->exists('installed.lock')) {
                $payload = json_decode(Storage::disk('local')->get('installed.lock'), true);
                if (is_array($payload) && ! empty($payload['version'])) {
                    $installedVersion = (string) $payload['version'];
                }
            }
        } catch (\Throwable) {
            // ignore and fall back
        }

        $configVersion = (string) config('app.version', config('update.current_version', '0.0.0'));
        if ($configVersion === '') {
            $configVersion = '0.0.0';
        }

        $packageVersion = null;
        try {
            $latestPath = base_path('updates/latest.json');
            if (file_exists($latestPath)) {
                $payload = json_decode(file_get_contents($latestPath), true);
                if (is_array($payload) && ! empty($payload['version'])) {
                    $packageVersion = (string) $payload['version'];
                }
            }
        } catch (\Throwable) {
            // ignore and fall back
        }

        $effectiveVersion = $packageVersion ?: $configVersion ?: ($installedVersion ?? '0.0.0');

        try {
            if ($installedVersion !== $effectiveVersion) {
                Storage::disk('local')->put(
                    'installed.lock',
                    json_encode([
                        'version' => $effectiveVersion,
                        'installed_at' => now()->toIso8601String(),
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
            }
        } catch (\Throwable) {
            // ignore write errors
        }

        return $effectiveVersion;
    }
}
