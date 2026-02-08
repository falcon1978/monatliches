<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UpdateFeedService;
use App\Services\UpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class UpdateController extends Controller
{
    public function __construct(private readonly UpdateFeedService $feedService)
    {
    }

    public function show()
    {
        try {
            $info = $this->feedService->getCachedInfo();
        } catch (Throwable $exception) {
            Log::error('Update check failed.', ['error' => $exception->getMessage()]);
            $info = null;
        }

        return view('admin.update', [
            'updateInfo' => $info,
            'installedVersion' => $this->feedService->currentVersion(),
            'updateLog' => Storage::disk('local')->exists('update/update.log')
                ? Storage::disk('local')->get('update/update.log')
                : null,
        ]);
    }

    public function check()
    {
        try {
            $info = $this->feedService->fetchInfo();
        } catch (Throwable $exception) {
            Log::error('Update check failed.', ['error' => $exception->getMessage()]);
            return back()->withErrors(['update' => 'Update-Check fehlgeschlagen. Bitte später erneut versuchen.']);
        }

        return back()->with('update_info', $info);
    }

    public function download()
    {
        try {
            $info = $this->feedService->fetchInfo();
            if (! $info['update_available']) {
                return back()->withErrors(['update' => 'Kein Update verfügbar.']);
            }

            if (empty($info['download_url'])) {
                throw new RuntimeException('Update-Informationen sind unvollständig.');
            }
        } catch (Throwable $exception) {
            Log::error('Updater failed.', ['error' => $exception->getMessage()]);
            return back()->withErrors(['update' => 'Update fehlgeschlagen. Bitte Logs prüfen.']);
        }

        return redirect()->away($info['download_url']);
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
        Storage::disk('local')->put('update/update.log', '['.now()->toDateTimeString().'] Update gestartet.');

        try {
            $updateDir = storage_path('app/update');
            File::ensureDirectoryExists($updateDir, 0775, true);
            if (! is_writable($updateDir)) {
                throw new RuntimeException('Update-Verzeichnis ist nicht beschreibbar.');
            }

            $zipFullPath = $updateDir.'/package.zip';
            $uploaded->move($updateDir, 'package.zip');
            Storage::disk('local')->append('update/update.log', '['.now()->toDateTimeString().'] ZIP gespeichert.');

            if (! file_exists($zipFullPath) || filesize($zipFullPath) === 0) {
                throw new RuntimeException('Upload ist leer oder konnte nicht gespeichert werden.');
            }

            app(UpdateService::class)->applyZip($zipFullPath);
        } catch (Throwable $exception) {
            Log::error('Updater failed.', ['error' => $exception->getMessage()]);
            Storage::disk('local')->append('update/update.log', '['.now()->toDateTimeString().'] Fehler: '.$exception->getMessage());
            return back()->withErrors(['update' => 'Update fehlgeschlagen. Bitte Logs prüfen.']);
        } finally {
            File::delete($lockPath);
        }

        return back()->with('status', 'Update installiert.');
    }

    public function auto()
    {
        try {
            $info = $this->feedService->fetchInfo();
            if (! $info['update_available']) {
                return back()->withErrors(['update' => 'Kein Update verfügbar.']);
            }

            if (empty($info['download_url'])) {
                throw new RuntimeException('Update-Informationen sind unvollständig.');
            }
        } catch (Throwable $exception) {
            Log::error('Auto-updater failed.', ['error' => $exception->getMessage()]);
            return back()->withErrors(['update' => 'Update fehlgeschlagen. Bitte Logs prüfen.']);
        }

        $lockPath = storage_path('app/update.lock');
        if (file_exists($lockPath)) {
            return back()->withErrors(['update' => 'Ein Update läuft bereits. Bitte später erneut versuchen.']);
        }

        File::put($lockPath, now()->toIso8601String());
        Storage::disk('local')->put('update/update.log', '['.now()->toDateTimeString().'] Auto-Update gestartet.');

        try {
            $updateDir = storage_path('app/update');
            File::ensureDirectoryExists($updateDir, 0775, true);
            if (! is_writable($updateDir)) {
                throw new RuntimeException('Update-Verzeichnis ist nicht beschreibbar.');
            }

            $zipFullPath = $updateDir.'/package.zip';
            $response = Http::timeout(120)
                ->withOptions(['allow_redirects' => true])
                ->sink($zipFullPath)
                ->get($info['download_url']);

            if (! $response->successful()) {
                throw new RuntimeException('Update-Download fehlgeschlagen.');
            }

            Storage::disk('local')->append('update/update.log', '['.now()->toDateTimeString().'] ZIP heruntergeladen.');

            if (! file_exists($zipFullPath) || filesize($zipFullPath) === 0) {
                throw new RuntimeException('Download ist leer oder konnte nicht gespeichert werden.');
            }

            if (! empty($info['sha256'])) {
                $expected = strtolower(trim((string) $info['sha256']));
                $actual = strtolower((string) hash_file('sha256', $zipFullPath));
                if ($expected !== '' && ! hash_equals($expected, $actual)) {
                    throw new RuntimeException('SHA256 stimmt nicht überein.');
                }
                Storage::disk('local')->append('update/update.log', '['.now()->toDateTimeString().'] SHA256 geprüft.');
            }

            app(UpdateService::class)->applyZip($zipFullPath);
        } catch (Throwable $exception) {
            Log::error('Auto-updater failed.', ['error' => $exception->getMessage()]);
            Storage::disk('local')->append('update/update.log', '['.now()->toDateTimeString().'] Fehler: '.$exception->getMessage());
            return back()->withErrors(['update' => 'Update fehlgeschlagen. Bitte Logs prüfen.']);
        } finally {
            File::delete($lockPath);
        }

        return back()->with('status', 'Update installiert.');
    }

}
