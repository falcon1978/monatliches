<x-install-layout>
    @include('install.partials.steps', ['step' => 0])

    <div class="mt-6">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Willkommen</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-slate-300">
            Der Installer prüft, ob die wichtigsten Voraussetzungen erfüllt sind.
        </p>
    </div>

    <div class="mt-6 space-y-3">
        @foreach ($checks as $check)
            <div class="flex items-start justify-between gap-4 rounded-md border border-gray-200 dark:border-slate-700/70 bg-gray-50/80 dark:bg-slate-800/60 px-4 py-3">
                <div>
                    <div class="text-sm font-medium text-gray-800 dark:text-slate-100">{{ $check['label'] }}</div>
                    @if ($check['value'])
                        <div class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ $check['value'] }}</div>
                    @endif
                </div>
                <span class="{{ $check['ok'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200' }} inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold">
                    {{ $check['ok'] ? 'OK' : 'Fehlt' }}
                </span>
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
        Hinweis: Wenn die App in einem Unterordner läuft (z. B. <code>/budget</code>), muss <strong>APP_URL</strong>
        diesen Pfad enthalten.
    </div>

    <div class="mt-6 flex items-center justify-between">
        @if ($allOk)
            <span class="text-sm text-emerald-700 dark:text-emerald-200">Alle Checks sind OK.</span>
            <a href="{{ route('install.database') }}" class="inline-flex items-center rounded-md bg-[var(--accent)] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-90">
                Weiter
            </a>
        @else
            <span class="text-sm text-rose-700 dark:text-rose-200">Bitte behebe die fehlenden Voraussetzungen.</span>
            <span class="text-sm text-gray-500 dark:text-slate-400">Danach Seite neu laden.</span>
        @endif
    </div>
</x-install-layout>
