<x-install-layout>
    @include('install.partials.steps', ['step' => 3])

    <div class="mt-6">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Datenbank einrichten</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-slate-300">
            Jetzt werden die Tabellen angelegt. Das kann einen Moment dauern.
        </p>
    </div>

    <div class="mt-4">
        <x-auth-session-status class="mb-3" :status="session('status')" />
        <x-input-error :messages="$errors->get('migrate')" class="mb-3" />
    </div>

    <form method="POST" action="{{ route('install.migrate.run') }}" class="mt-4 flex items-center justify-between">
        @csrf
        <a href="{{ route('install.app') }}" class="text-sm text-gray-600 hover:text-gray-900 dark:text-slate-300 dark:hover:text-white">
            Zurück
        </a>
        <x-primary-button>
            Migration starten
        </x-primary-button>
    </form>
</x-install-layout>
