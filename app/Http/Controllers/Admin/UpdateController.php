<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class UpdateController extends Controller
{
    public function show()
    {
        return view('admin.update');
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

            $this->applyZip($zipFullPath);
        } catch (Throwable $exception) {
            Log::error('Updater failed.', ['error' => $exception->getMessage()]);
            return back()->withErrors(['update' => 'Update fehlgeschlagen. Bitte Logs prüfen.']);
        } finally {
            File::delete($lockPath);
        }

        return back()->with('status', 'Update erfolgreich eingespielt.');
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

            $this->applyZip($zipFullPath);
        } catch (Throwable $exception) {
            Log::error('Updater failed.', ['error' => $exception->getMessage()]);
            return back()->withErrors(['update' => 'Update fehlgeschlagen. Bitte Logs prüfen.']);
        } finally {
            File::delete($lockPath);
        }

        return back()->with('status', 'Update erfolgreich eingespielt.');
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

    private function applyZip(string $zipFullPath): void
    {
        $tempRoot = storage_path('app/update/'.Str::uuid()->toString());
        File::ensureDirectoryExists($tempRoot);

        $extractPath = $tempRoot.'/extracted';
        File::ensureDirectoryExists($extractPath);

        $zip = new ZipArchive();
        $openResult = $zip->open($zipFullPath);
        if ($openResult !== true) {
            throw new RuntimeException($this->zipOpenErrorMessage($openResult));
        }

        if (! $zip->extractTo($extractPath)) {
            throw new RuntimeException('ZIP konnte nicht entpackt werden.');
        }
        $zip->close();

        $sourceRoot = $this->detectSourceRoot($extractPath);
        $this->applyUpdate($sourceRoot, base_path());

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('optimize:clear');
    }

    private function zipOpenErrorMessage(int $code): string
    {
        $messages = [
            ZipArchive::ER_EXISTS => 'ZIP konnte nicht geöffnet werden (Datei existiert bereits).',
            ZipArchive::ER_INCONS => 'ZIP ist inkonsistent oder beschädigt.',
            ZipArchive::ER_INVAL => 'ZIP ist ungültig.',
            ZipArchive::ER_MEMORY => 'ZIP konnte nicht geöffnet werden (Speicherfehler).',
            ZipArchive::ER_NOENT => 'ZIP wurde nicht gefunden.',
            ZipArchive::ER_NOZIP => 'Datei ist kein ZIP.',
            ZipArchive::ER_OPEN => 'ZIP konnte nicht geöffnet werden.',
            ZipArchive::ER_READ => 'ZIP konnte nicht gelesen werden.',
            ZipArchive::ER_SEEK => 'ZIP konnte nicht gelesen werden (Seek-Fehler).',
        ];

        return $messages[$code] ?? 'ZIP konnte nicht geöffnet werden.';
    }

    private function detectSourceRoot(string $extractPath): string
    {
        $dirs = File::directories($extractPath);
        $files = File::files($extractPath);

        if (count($dirs) === 1 && count($files) === 0) {
            return $dirs[0];
        }

        return $extractPath;
    }

    private function applyUpdate(string $sourceRoot, string $targetRoot): void
    {
        $allowed = [
            'app',
            'bootstrap',
            'config',
            'database/migrations',
            'public',
            'resources',
            'routes',
            'vendor',
            'artisan',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'vite.config.js',
            'postcss.config.js',
            'tailwind.config.js',
            'phpunit.xml',
            'LICENSE',
            'README.md',
            'CHANGELOG.md',
            'SECURITY.md',
            '.htaccess',
            'public/.htaccess',
            '.gitattributes',
            '.editorconfig',
            '.gitignore',
            'tools',
        ];

        $excludedPrefixes = [
            'storage/',
            'node_modules/',
            'dist/',
            '.git/',
            '.env',
        ];

        foreach ($allowed as $relative) {
            $source = $sourceRoot.'/'.$relative;
            $target = $targetRoot.'/'.$relative;

            if (! file_exists($source)) {
                continue;
            }

            if (is_dir($source)) {
                $this->copyDirectory($source, $target, $excludedPrefixes);
                continue;
            }

            if ($this->isExcluded($relative, $excludedPrefixes)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            File::copy($source, $target);
        }
    }

    private function copyDirectory(string $source, string $target, array $excludedPrefixes): void
    {
        $files = File::allFiles($source, true);

        foreach ($files as $file) {
            $relative = Str::replaceFirst($source.'/', '', $file->getPathname());
            $targetPath = $target.'/'.$relative;

            if ($this->isExcluded($relative, $excludedPrefixes)) {
                continue;
            }

            if (Str::startsWith($relative, 'cache/') && Str::endsWith($relative, '.php') && Str::contains($target, 'bootstrap')) {
                continue;
            }

            File::ensureDirectoryExists(dirname($targetPath));
            File::copy($file->getPathname(), $targetPath);
        }
    }

    private function isExcluded(string $relative, array $excludedPrefixes): bool
    {
        foreach ($excludedPrefixes as $prefix) {
            if (Str::startsWith($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
