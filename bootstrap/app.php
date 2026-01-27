<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Ensure a stable session cookie name during install before .env is created.
$installedLockPath = __DIR__.'/../storage/app/installed.lock';
if (! is_file($installedLockPath)
    && ! isset($_ENV['SESSION_COOKIE'])
    && ! isset($_SERVER['SESSION_COOKIE'])
    && getenv('SESSION_COOKIE') === false) {
    $_ENV['SESSION_COOKIE'] = 'installer_session';
    $_SERVER['SESSION_COOKIE'] = 'installer_session';
}

// Ensure an APP_KEY exists during install before .env is created.
$envCandidates = [
    __DIR__.'/../storage/app/installer.env',
    __DIR__.'/../.env',
];
$hasEnvKey = false;
foreach ($envCandidates as $candidate) {
    if (! is_file($candidate)) {
        continue;
    }
    $contents = file_get_contents($candidate);
    if ($contents !== false && preg_match('/^APP_KEY=(.+)$/m', $contents, $matches)) {
        $hasEnvKey = trim($matches[1]) !== '';
        if ($hasEnvKey) {
            break;
        }
    }
}

if (! $hasEnvKey && ! isset($_ENV['APP_KEY']) && ! isset($_SERVER['APP_KEY']) && getenv('APP_KEY') === false) {
    $installerKeyPath = __DIR__.'/../storage/app/installer.key';
    $key = null;

    if (is_file($installerKeyPath)) {
        $key = trim((string) file_get_contents($installerKeyPath));
    }

    if (! $key) {
        $key = 'base64:'.base64_encode(random_bytes(32));
        @file_put_contents($installerKeyPath, $key);
    }

    $_ENV['APP_KEY'] = $key;
    $_SERVER['APP_KEY'] = $key;
}

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'installed' => App\Http\Middleware\EnsureInstalled::class,
            'notInstalled' => App\Http\Middleware\RedirectIfInstalled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

$installerEnvPath = __DIR__.'/../storage/app/installer.env';
if (is_file($installerEnvPath)) {
    $installerEnvContents = file_get_contents($installerEnvPath);
    if ($installerEnvContents !== false && trim($installerEnvContents) !== '') {
        $app->useEnvironmentPath(dirname($installerEnvPath));
        $app->loadEnvironmentFrom(basename($installerEnvPath));
    }
}

return $app;
