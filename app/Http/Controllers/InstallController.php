<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Installer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use PDO;
use RuntimeException;
use Throwable;

class InstallController extends Controller
{
    public function welcome(Request $request)
    {
        $checks = $this->systemChecks();

        return view('install.welcome', [
            'checks' => $checks,
            'allOk' => collect($checks)->every(fn (array $check) => $check['ok']),
        ]);
    }

    public function database(Request $request)
    {
        return view('install.database', [
            'db' => $request->session()->get('install.db', []),
            'verified' => (bool) $request->session()->get('install.db_verified', false),
        ]);
    }

    public function testDatabase(Request $request)
    {
        $data = $request->validate([
            'host' => ['required', 'string'],
            'port' => ['nullable', 'integer'],
            'database' => ['required', 'string'],
            'username' => ['required', 'string'],
            'password' => ['nullable', 'string'],
        ]);

        $data['port'] = $data['port'] ?: 3306;

        try {
            $this->testPdoConnection($data);
        } catch (Throwable $exception) {
            Log::error('Installer DB connection failed.', [
                'host' => $data['host'],
                'database' => $data['database'],
                'error' => $exception->getMessage(),
            ]);

            $request->session()->put('install.db_verified', false);

            return back()
                ->withErrors(['db' => 'Verbindung fehlgeschlagen. Bitte Zugangsdaten prüfen.'])
                ->withInput();
        }

        $request->session()->put('install.db', $data);
        $request->session()->put('install.db_verified', true);

        return back()->with('status', 'Verbindung erfolgreich hergestellt.');
    }

    public function app(Request $request)
    {
        if (! $request->session()->get('install.db_verified')) {
            return redirect()->route('install.database');
        }

        return view('install.app', [
            'appUrl' => $request->session()->get('install.app.url', $this->guessAppUrl($request)),
            'appName' => $request->session()->get('install.app.name', config('app.name', 'Monatliches')),
        ]);
    }

    public function storeApp(Request $request)
    {
        if (! $request->session()->get('install.db_verified')) {
            return redirect()->route('install.database');
        }

        $data = $request->validate([
            'app_url' => ['required', 'url'],
            'app_name' => ['required', 'string', 'max:255'],
        ]);

        $db = $request->session()->get('install.db');
        if (! $db) {
            return redirect()->route('install.database');
        }

        try {
            $this->writeEnv($db, $data);
            Artisan::call('config:clear');
        } catch (Throwable $exception) {
            Log::error('Installer .env write failed.', [
                'error' => $exception->getMessage(),
            ]);

            return back()->withErrors(['app' => 'Konnte .env nicht schreiben. Bitte Schreibrechte prüfen.']);
        }

        $request->session()->put('install.app', [
            'url' => $data['app_url'],
            'name' => $data['app_name'],
        ]);
        $request->session()->put('install.app_configured', true);

        return redirect()->route('install.migrate');
    }

    public function migrate(Request $request)
    {
        if (! $request->session()->get('install.app_configured')) {
            return redirect()->route('install.app');
        }

        return view('install.migrate');
    }

    public function runMigrations(Request $request)
    {
        if (! $request->session()->get('install.app_configured')) {
            return redirect()->route('install.app');
        }

        try {
            $exit = Artisan::call('migrate', ['--force' => true]);
            if ($exit !== 0) {
                throw new RuntimeException('Migration exit code '.$exit);
            }

            Artisan::call('optimize:clear');
        } catch (Throwable $exception) {
            Log::error('Installer migration failed.', [
                'error' => $exception->getMessage(),
            ]);

            return back()->withErrors(['migrate' => 'Migration fehlgeschlagen. Bitte Logs prüfen.']);
        }

        $request->session()->put('install.migrated', true);

        return redirect()->route('install.admin')
            ->with('status', 'Datenbank wurde eingerichtet.');
    }

    public function admin(Request $request)
    {
        if (! $request->session()->get('install.migrated')) {
            return redirect()->route('install.migrate');
        }

        return view('install.admin');
    }

    public function storeAdmin(Request $request)
    {
        if (! $request->session()->get('install.migrated')) {
            return redirect()->route('install.migrate');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        try {
            User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_admin' => true,
            ]);
        } catch (Throwable $exception) {
            Log::error('Installer admin creation failed.', [
                'error' => $exception->getMessage(),
            ]);

            return back()->withErrors(['admin' => 'Admin-Benutzer konnte nicht erstellt werden. Bitte Logs prüfen.']);
        }

        $request->session()->put('install.admin_created', true);

        return redirect()->route('install.finish');
    }

    public function finish(Request $request)
    {
        if (! $request->session()->get('install.admin_created')) {
            return redirect()->route('install.admin');
        }

        $envError = null;

        try {
            $this->ensureEnvKey();
            Storage::disk('local')->delete('installer.key');
        } catch (Throwable $exception) {
            Log::error('Installer APP_KEY write failed.', [
                'error' => $exception->getMessage(),
            ]);
            $envError = 'APP_KEY konnte nicht gespeichert werden. Bitte Schreibrechte prüfen und neu laden.';
        }

        if ($envError === null) {
            if (! Installer::isInstalled()) {
                Installer::writeInstalledLock();
            }

            $request->session()->forget('install');
        }

        return view('install.finish', [
            'envError' => $envError,
        ]);
    }

    private function systemChecks(): array
    {
        $checks = [];

        $checks[] = [
            'label' => 'PHP-Version (>= 8.2)',
            'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'value' => PHP_VERSION,
        ];

        $extensions = [
            'pdo',
            'pdo_mysql',
            'mbstring',
            'openssl',
            'tokenizer',
            'xml',
            'ctype',
            'json',
            'zip',
        ];

        foreach ($extensions as $extension) {
            $checks[] = [
                'label' => 'Extension: '.$extension,
                'ok' => extension_loaded($extension),
                'value' => null,
            ];
        }

        $checks[] = [
            'label' => 'Schreibrechte: storage/',
            'ok' => is_writable(storage_path()),
            'value' => storage_path(),
        ];

        $bootstrapCache = base_path('bootstrap/cache');
        $checks[] = [
            'label' => 'Schreibrechte: bootstrap/cache/',
            'ok' => is_writable($bootstrapCache),
            'value' => $bootstrapCache,
        ];

        return $checks;
    }

    private function testPdoConnection(array $data): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $data['host'],
            $data['port'],
            $data['database']
        );

        $pdo = new PDO($dsn, $data['username'], $data['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        $pdo->query('SELECT 1');
    }

    private function guessAppUrl(Request $request): string
    {
        $basePath = rtrim($request->getBasePath(), '/');

        return $basePath === ''
            ? $request->getSchemeAndHttpHost()
            : $request->getSchemeAndHttpHost().$basePath;
    }

    private function writeEnv(array $db, array $app): void
    {
        $envPath = $this->resolveEnvPath();
        $contents = file_get_contents($envPath);

        if ($contents === false) {
            throw new RuntimeException('Unable to read .env file.');
        }

        $values = [
            'APP_NAME' => $app['app_name'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $app['app_url'],
            'SESSION_COOKIE' => Str::slug($app['app_name'], '_').'_session',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => (string) ($db['password'] ?? ''),
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
        ];

        foreach ($values as $key => $value) {
            $contents = $this->setEnvValue($contents, $key, $value);
        }

        if (file_put_contents($envPath, $contents) === false) {
            throw new RuntimeException('Unable to write .env file.');
        }
    }

    private function ensureEnvKey(): void
    {
        $envPath = $this->resolveEnvPath();
        $contents = file_get_contents($envPath);
        if ($contents === false) {
            throw new RuntimeException('Unable to read .env file.');
        }

        if (preg_match('/^APP_KEY=(.*)$/m', $contents, $matches)) {
            if (trim($matches[1]) !== '') {
                return;
            }
        }

        $contents = $this->setEnvValue($contents, 'APP_KEY', $this->generateAppKey());

        if (file_put_contents($envPath, $contents) === false) {
            throw new RuntimeException('Unable to write .env file.');
        }
    }

    private function resolveEnvPath(): string
    {
        $rootEnv = base_path('.env');
        $fallbackEnv = storage_path('app/installer.env');

        if (file_exists($rootEnv)) {
            if (is_writable($rootEnv)) {
                return $rootEnv;
            }

            Log::warning('Installer .env not writable, using fallback.', [
                'path' => $rootEnv,
            ]);
        } else {
            $examplePath = base_path('.env.example');

            if (file_exists($examplePath)) {
                $rootDir = dirname($rootEnv);
                if (is_writable($rootDir)) {
                    if (@copy($examplePath, $rootEnv)) {
                        return $rootEnv;
                    }
                }
            }
        }

        $storageDir = storage_path('app');
        if (! is_dir($storageDir)) {
            if (! @mkdir($storageDir, 0775, true) && ! is_dir($storageDir)) {
                throw new RuntimeException('storage/app konnte nicht erstellt werden.');
            }
        }

        if (! is_writable($storageDir)) {
            throw new RuntimeException('storage/app ist nicht schreibbar.');
        }

        if (file_exists($fallbackEnv)) {
            if (! is_writable($fallbackEnv)) {
                throw new RuntimeException('Fallback .env ist nicht schreibbar.');
            }

            return $fallbackEnv;
        }

        $examplePath = base_path('.env.example');
        if (file_exists($examplePath)) {
            if (@copy($examplePath, $fallbackEnv)) {
                return $fallbackEnv;
            }
        }

        if (@file_put_contents($fallbackEnv, '') !== false) {
            return $fallbackEnv;
        }

        throw new RuntimeException('Fallback .env konnte nicht erstellt werden.');
    }

    private function setEnvValue(string $contents, string $key, string $value): string
    {
        $line = $key.'='.$this->formatEnvValue($value);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents)) {
            return preg_replace($pattern, $line, $contents);
        }

        return rtrim($contents).PHP_EOL.$line.PHP_EOL;
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/\s|"|\'|=|#/', $value)) {
            $escaped = str_replace('"', '\\"', $value);

            return '"'.$escaped.'"';
        }

        return $value;
    }

    private function generateAppKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }
}
