<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class UpdateController extends Controller
{
    public function show()
    {
        try {
            $info = $this->fetchUpdateInfo();
        } catch (Throwable $exception) {
            Log::error('Update check failed.', ['error' => $exception->getMessage()]);
            $info = null;
        }

        return view('admin.update', [
            'updateInfo' => $info,
        ]);
    }

    public function check()
    {
        try {
            $info = $this->fetchUpdateInfo();
        } catch (Throwable $exception) {
            Log::error('Update check failed.', ['error' => $exception->getMessage()]);
            return back()->withErrors(['update' => 'Update-Check fehlgeschlagen. Bitte später erneut versuchen.']);
        }

        return back()->with('update_info', $info);
    }

    public function download()
    {
        $lockPath = storage_path('app/update.lock');
        if (file_exists($lockPath)) {
            return back()->withErrors(['update' => 'Ein Update läuft bereits. Bitte später erneut versuchen.']);
        }

        File::put($lockPath, now()->toIso8601String());

        try {
            $info = $this->fetchUpdateInfo();
            if (! $info['update_available']) {
                return back()->withErrors(['update' => 'Kein Update verfügbar.']);
            }

            if (empty($info['download_url']) || empty($info['sha256'])) {
                throw new RuntimeException('Update-Informationen sind unvollständig.');
            }

            $updateDir = storage_path('app/update');
            File::ensureDirectoryExists($updateDir, 0775, true);
            if (! is_writable($updateDir)) {
                throw new RuntimeException('Update-Verzeichnis ist nicht beschreibbar.');
            }

            $zipFullPath = $updateDir.'/package.zip';
            $response = Http::timeout(60)->withOptions(['stream' => true])->get($info['download_url']);
            if (! $response->successful()) {
                throw new RuntimeException('Update konnte nicht heruntergeladen werden.');
            }

            File::put($zipFullPath, $response->body());
            if (! file_exists($zipFullPath) || filesize($zipFullPath) === 0) {
                throw new RuntimeException('Update-Datei ist leer.');
            }

            $hash = hash_file('sha256', $zipFullPath);
            if (! hash_equals(strtolower($info['sha256']), strtolower($hash))) {
                throw new RuntimeException('Update-Hash stimmt nicht überein.');
            }
        } catch (Throwable $exception) {
            Log::error('Updater failed.', ['error' => $exception->getMessage()]);
            return back()->withErrors(['update' => 'Update fehlgeschlagen. Bitte Logs prüfen.']);
        } finally {
            File::delete($lockPath);
        }

        return back()->with('status', 'Update heruntergeladen. Bitte per CLI anwenden: php artisan app:apply-update');
    }

    public function run(Request $request)
    {
        $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'max:512000'],
        ]);

        $uploaded = $request->file('package');
        if (! $uploaded || ! $uploaded->isValid()) {
            $code = $uploaded ? (int) $uploaded->getError() : null;
            Log::warning('Updater upload invalid.', ['error_code' => $code]);
            return back()->withErrors(['update' => 'Upload fehlgeschlagen. Bitte erneut versuchen.']);
        }

        $lockPath = storage_path('app/update.lock');
        if (file_exists($lockPath)) {
            return back()->withErrors(['update' => 'Ein Update läuft bereits. Bitte später erneut versuchen.']);
        }

        File::put($lockPath, now()->toIso8601String());

        try {
            $updateDir = storage_path('app/update');
            File::ensureDirectoryExists($updateDir, 0775, true);
            if (! is_writable($updateDir)) {
                throw new RuntimeException('Update-Verzeichnis ist nicht beschreibbar.');
            }

            $zipFullPath = $updateDir.'/package.zip';
            $uploaded->move($updateDir, 'package.zip');

            if (! file_exists($zipFullPath) || filesize($zipFullPath) === 0) {
                throw new RuntimeException('Upload ist leer oder konnte nicht gespeichert werden.');
            }
        } catch (Throwable $exception) {
            Log::error('Updater failed.', ['error' => $exception->getMessage()]);
            return back()->withErrors(['update' => 'Update fehlgeschlagen. Bitte Logs prüfen.']);
        } finally {
            File::delete($lockPath);
        }

        return back()->with('status', 'Update hochgeladen. Bitte per CLI anwenden: php artisan app:apply-update');
    }

    private function fetchUpdateInfo(): array
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
