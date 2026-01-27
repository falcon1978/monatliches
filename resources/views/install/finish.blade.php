<x-install-layout>
    @include('install.partials.steps', ['step' => 5])

    <div class="mt-6">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Fertig!</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-slate-300">
            Die Installation ist abgeschlossen. Du kannst dich jetzt anmelden.
        </p>
    </div>

    @if ($envError)
        <div class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-100">
            {{ $envError }}
        </div>
    @else
        <div class="mt-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100">
            Installer wurde abgeschlossen und gesperrt.
        </div>

        <div class="mt-6 flex items-center justify-end">
            <a href="{{ route('login') }}" class="inline-flex items-center rounded-md bg-[var(--accent)] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-90">
                Zum Login
            </a>
        </div>
    @endif
</x-install-layout>
