<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class AiClient
{
    public function signPayload(array $payload): array
    {
        $timestamp = (string) now()->timestamp;
        $bodyJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp.'.'.$bodyJson, (string) config('services.ai.hmac_secret', ''));

        return [
            'timestamp' => $timestamp,
            'signature' => $signature,
            'bodyJson' => $bodyJson,
        ];
    }

    public function post(string $path, array $payload): array
    {
        if (! $this->isEnabled()) {
            return [
                'ok' => false,
                'status' => null,
                'data' => null,
                'error' => 'disabled',
            ];
        }

        $baseUrl = rtrim((string) config('services.ai.base_url', ''), '/');
        $secret = (string) config('services.ai.hmac_secret', '');
        if ($baseUrl === '' || $secret === '') {
            Log::warning('AI client skipped because base URL or secret is missing.');

            return [
                'ok' => false,
                'status' => null,
                'data' => null,
                'error' => 'config_missing',
            ];
        }

        try {
            $signed = $this->signPayload($payload);
        } catch (JsonException $exception) {
            Log::warning('AI payload could not be encoded.', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => null,
                'data' => null,
                'error' => 'payload_encoding_failed',
            ];
        }

        $url = $baseUrl.'/'.ltrim($path, '/');

        try {
            $response = Http::timeout($this->timeoutSeconds())
                ->withHeaders([
                    'X-AI-Timestamp' => $signed['timestamp'],
                    'X-AI-Signature' => $signed['signature'],
                    'Accept' => 'application/json',
                ])
                ->withBody($signed['bodyJson'], 'application/json')
                ->post($url);
        } catch (ConnectionException $exception) {
            Log::warning('AI request failed with connection/timeout error.', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => null,
                'data' => null,
                'error' => 'timeout_or_connection',
            ];
        }

        if ($response->failed()) {
            Log::warning('AI request returned error response.', [
                'path' => $path,
                'status' => $response->status(),
                'body_excerpt' => mb_substr($response->body(), 0, 500),
            ]);

            return [
                'ok' => false,
                'status' => $response->status(),
                'data' => null,
                'error' => 'http_error',
            ];
        }

        try {
            $decoded = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::warning('AI response body is not valid JSON.', [
                'path' => $path,
                'status' => $response->status(),
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => $response->status(),
                'data' => null,
                'error' => 'invalid_json',
            ];
        }

        if (! is_array($decoded)) {
            Log::warning('AI response JSON is not an object.', [
                'path' => $path,
                'status' => $response->status(),
            ]);

            return [
                'ok' => false,
                'status' => $response->status(),
                'data' => null,
                'error' => 'invalid_structure',
            ];
        }

        return [
            'ok' => true,
            'status' => $response->status(),
            'data' => $decoded,
            'error' => null,
        ];
    }

    private function isEnabled(): bool
    {
        return (bool) config('services.ai.enabled', false);
    }

    private function timeoutSeconds(): int
    {
        return max(1, (int) config('services.ai.timeout_seconds', 20));
    }
}
