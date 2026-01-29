<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class UpdateFeedService
{
    public function getCachedInfo(int $ttlSeconds = 21600): ?array
    {
        $cacheKey = 'update.feed.info';

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
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

        $current = config('update.current_version', '0.0.0');
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
}
