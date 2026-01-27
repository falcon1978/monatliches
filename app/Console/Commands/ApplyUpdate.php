<?php

namespace App\Console\Commands;

use App\Services\UpdateService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ApplyUpdate extends Command
{
    protected $signature = 'app:apply-update {--path= : Pfad zur Update-ZIP (Standard: storage/app/update/package.zip)}';

    protected $description = 'Spielt ein heruntergeladenes Update-ZIP ein (CLI-only).';

    public function handle(UpdateService $service): int
    {
        $path = $this->option('path') ?: storage_path('app/update/package.zip');

        if (! is_file($path)) {
            $this->error('Update-ZIP nicht gefunden: '.$path);
            return self::FAILURE;
        }

        try {
            $service->applyZip($path);
        } catch (Throwable $exception) {
            $this->error('Update fehlgeschlagen: '.$exception->getMessage());
            return self::FAILURE;
        }

        $this->info('Update erfolgreich eingespielt.');
        return self::SUCCESS;
    }
}
