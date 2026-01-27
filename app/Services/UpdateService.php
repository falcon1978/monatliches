<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class UpdateService
{
    public function applyZip(string $zipFullPath): void
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
