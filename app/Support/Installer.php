<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class Installer
{
    public static function isInstalled(): bool
    {
        if (Storage::disk('local')->exists('installed.lock')) {
            return true;
        }

        if (self::detectExistingInstall()) {
            self::writeInstalledLock();
            return true;
        }

        return false;
    }

    public static function writeInstalledLock(): void
    {
        $payload = [
            'version' => config('app.version', '1.0.0'),
            'installed_at' => now()->toIso8601String(),
        ];

        Storage::disk('local')->put(
            'installed.lock',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        Storage::disk('local')->delete('installer.key');
    }

    private static function detectExistingInstall(): bool
    {
        if (! file_exists(base_path('.env'))) {
            return false;
        }

        try {
            DB::connection()->getPdo();

            if (! Schema::hasTable('users')) {
                return false;
            }

            return User::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
