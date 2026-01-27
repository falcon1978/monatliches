<x-install-layout>
    @include('install.partials.steps', ['step' => 2])

    <div class="mt-6">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">App konfigurieren</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-slate-300">
            Lege die Basis-URL fest. Diese muss einen Unterordner enthalten, falls die App nicht im Root läuft.
        </p>
    </div>

    <div class="mt-4">
        <x-input-error :messages="$errors->get('app')" class="mb-3" />
    </div>

    <form method="POST" action="{{ route('install.app.store') }}" class="mt-2 space-y-4">
        @csrf

        <div>
            <x-input-label for="app_url" value="APP_URL" />
            <x-text-input id="app_url" class="block mt-1 w-full" type="url" name="app_url" :value="old('app_url', $appUrl)" required />
            <x-input-error :messages="$errors->get('app_url')" class="mt-2" />
            <p class="mt-2 text-xs text-gray-500 dark:text-slate-400">
                Beispiel: https://example.com/budget
            </p>
        </div>

        <div>
            <x-input-label for="app_name" value="APP_NAME" />
            <x-text-input id="app_name" class="block mt-1 w-full" type="text" name="app_name" :value="old('app_name', $appName)" required />
            <x-input-error :messages="$errors->get('app_name')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('install.database') }}" class="text-sm text-gray-600 hover:text-gray-900 dark:text-slate-300 dark:hover:text-white">
                Zurück
            </a>
            <x-primary-button>
                Speichern &amp; weiter
            </x-primary-button>
        </div>
    </form>
</x-install-layout>
