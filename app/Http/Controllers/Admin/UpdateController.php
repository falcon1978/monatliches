<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UpdateFeedService;
use App\Services\UpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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

            app(UpdateService::class)->applyZip($zipFullPath);
        } catch (Throwable $exception) {
            Log::error('Updater failed.', ['error' => $exception->getMessage()]);
            return back()->withErrors(['update' => 'Update fehlgeschlagen. Bitte Logs prüfen.']);
        } finally {
            File::delete($lockPath);
        }

        return back()->with('status', 'Update installiert.');
    }

}
