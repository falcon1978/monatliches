<x-install-layout>
    @include('install.partials.steps', ['step' => 1])

    <div class="mt-6">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Datenbank konfigurieren</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-slate-300">
            Bitte trage die Zugangsdaten deiner MySQL-Datenbank ein und teste die Verbindung.
        </p>
    </div>

    <div class="mt-4">
        <x-auth-session-status class="mb-3" :status="session('status')" />
        <x-input-error :messages="$errors->get('db')" class="mb-3" />
    </div>

    <form method="POST" action="{{ route('install.database.test') }}" class="mt-2 space-y-4">
        @csrf

        <div>
            <x-input-label for="db_host" value="Host" />
            <x-text-input id="db_host" class="block mt-1 w-full" type="text" name="host" :value="old('host', $db['host'] ?? '127.0.0.1')" required />
            <x-input-error :messages="$errors->get('host')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="db_port" value="Port" />
            <x-text-input id="db_port" class="block mt-1 w-full" type="number" name="port" :value="old('port', $db['port'] ?? 3306)" />
            <x-input-error :messages="$errors->get('port')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="db_name" value="Datenbank" />
            <x-text-input id="db_name" class="block mt-1 w-full" type="text" name="database" :value="old('database', $db['database'] ?? '')" required />
            <x-input-error :messages="$errors->get('database')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="db_user" value="Benutzername" />
            <x-text-input id="db_user" class="block mt-1 w-full" type="text" name="username" :value="old('username', $db['username'] ?? '')" required />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="db_password" value="Passwort" />
            <x-text-input id="db_password" class="block mt-1 w-full" type="password" name="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between pt-2">
            <x-primary-button>
                Verbindung testen
            </x-primary-button>

            @if ($verified)
                <a href="{{ route('install.app') }}" class="inline-flex items-center rounded-md bg-[var(--accent)] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-90">
                    Weiter
                </a>
            @endif
        </div>
    </form>
</x-install-layout>
