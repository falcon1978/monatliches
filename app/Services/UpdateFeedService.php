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

        $packageVersion = $this->readVersionFromLatestJson(base_path('updates/latest.json'));
        $envExampleVersion = $this->readVersionFromEnvExample(base_path('.env.example'));
        $changelogVersion = $this->readVersionFromChangelog(base_path('CHANGELOG.md'));

        $candidates = array_filter([
            $packageVersion,
            $envExampleVersion,
            $changelogVersion,
            $configVersion,
            $installedVersion,
        ], static fn ($value) => is_string($value) && $value !== '');

        $effectiveVersion = '0.0.0';
        foreach ($candidates as $candidate) {
            if (version_compare($candidate, $effectiveVersion, '>')) {
                $effectiveVersion = $candidate;
            }
        }

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

    private function readVersionFromLatestJson(string $path): ?string
    {
        try {
            if (! file_exists($path)) {
                return null;
            }
            $payload = json_decode(file_get_contents($path), true);
            if (! is_array($payload) || empty($payload['version'])) {
                return null;
            }
            return (string) $payload['version'];
        } catch (\Throwable) {
            return null;
        }
    }

    private function readVersionFromEnvExample(string $path): ?string
    {
        try {
            if (! file_exists($path)) {
                return null;
            }
            $contents = file_get_contents($path);
            if ($contents === false) {
                return null;
            }
            if (! preg_match('/^APP_VERSION\\s*=\\s*(.+)$/m', $contents, $matches)) {
                return null;
            }
            $value = trim($matches[1]);
            $value = trim($value, "\"' ");
            return $value !== '' ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function readVersionFromChangelog(string $path): ?string
    {
        try {
            if (! file_exists($path)) {
                return null;
            }
            $contents = file_get_contents($path);
            if ($contents === false) {
                return null;
            }
            if (! preg_match('/^##\\s+v?([0-9]+\\.[0-9]+\\.[0-9]+)/m', $contents, $matches)) {
                return null;
            }
            return $matches[1] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
