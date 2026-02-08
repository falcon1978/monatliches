<x-app-layout :mobile-title="'Hilfe & Docs'">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Hilfe & Docs</h2>
            <a href="{{ route('months.create') }}" class="inline-flex items-center px-3 py-1.5 bg-[var(--accent)] text-white rounded text-sm">Monat anlegen</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="w-full px-4 sm:px-6 lg:px-10 space-y-6">
            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Kurzstart in 4 Schritten</h3>
                <ol class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600 dark:text-slate-300">
                    <li class="rounded-xl border border-[var(--border)] bg-white/70 dark:bg-slate-900/60 p-4">
                        <div class="text-[10px] uppercase tracking-[0.25em] text-gray-500">Schritt 1</div>
                        <div class="mt-1 font-semibold text-gray-900 dark:text-slate-100">Monat anlegen</div>
                        <div class="mt-1">Zeitraum, Name und Tages‑Lebensunterhalt setzen.</div>
                    </li>
                    <li class="rounded-xl border border-[var(--border)] bg-white/70 dark:bg-slate-900/60 p-4">
                        <div class="text-[10px] uppercase tracking-[0.25em] text-gray-500">Schritt 2</div>
                        <div class="mt-1 font-semibold text-gray-900 dark:text-slate-100">Konten erfassen</div>
                        <div class="mt-1">Ist‑Konten, erwartete Beträge und Verrechnungskonten anlegen.</div>
                    </li>
                    <li class="rounded-xl border border-[var(--border)] bg-white/70 dark:bg-slate-900/60 p-4">
                        <div class="text-[10px] uppercase tracking-[0.25em] text-gray-500">Schritt 3</div>
                        <div class="mt-1 font-semibold text-gray-900 dark:text-slate-100">Einträge hinzufügen</div>
                        <div class="mt-1">Einnahmen, Rechnungen und Fixkosten eintragen – sortierbar.</div>
                    </li>
                    <li class="rounded-xl border border-[var(--border)] bg-white/70 dark:bg-slate-900/60 p-4">
                        <div class="text-[10px] uppercase tracking-[0.25em] text-gray-500">Schritt 4</div>
                        <div class="mt-1 font-semibold text-gray-900 dark:text-slate-100">Monat prüfen</div>
                        <div class="mt-1">Bezahlt markieren, offene Posten übertragen oder archivieren.</div>
                    </li>
                </ol>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Grundfunktionen</h3>
                    <ul class="mt-4 space-y-2 text-sm text-gray-600 dark:text-slate-300">
                        <li>Monatsübersicht zeigt Ergebnis, Einnahmen, Ausgaben und Lebensunterhalt auf einen Blick.</li>
                        <li>Kontostände werden im aktuellen Monat ins Ergebnis eingerechnet.</li>
                        <li>Wiederkehrende Posten übernehmen Vorlagen automatisch in neue Monate.</li>
                        <li>Offene Posten lassen sich in den nächsten Monat übertragen.</li>
                        <li>Ferien reduzieren Lebensunterhalt und Arbeitstage (v. a. für Selbstständige).</li>
                    </ul>
                </div>

                <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Updates & Betrieb</h3>
                    <ul class="mt-4 space-y-2 text-sm text-gray-600 dark:text-slate-300">
                        <li>Updates lassen sich direkt im Update‑Dialog herunterladen und installieren.</li>
                        <li>Alternativ kannst du den Release‑ZIP über GitHub beziehen.</li>
                        <li>Gehostete Versionen werden zentral gepflegt.</li>
                    </ul>
                    @can('viewAny', App\Models\User::class)
                        <div class="mt-4">
                            <a href="{{ route('admin.update.show') }}" class="inline-flex items-center px-3 py-1.5 bg-[var(--accent)] text-white rounded text-sm">Zum Update‑Dialog</a>
                        </div>
                    @endcan
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">App‑Verhalten & Kontostand</h3>
                <ul class="mt-4 space-y-2 text-sm text-gray-600 dark:text-slate-300">
                    <li>Kontostände kannst du jederzeit manuell anpassen – so musst du nicht jede Kleinigkeit erfassen.</li>
                    <li>Kontostände werden in jedem Monat angezeigt, ins Ergebnis zählt aber nur der aktuell markierte Monat.</li>
                    <li>Der aktuelle Monat bleibt aktiv, bis du den Übertrag machst oder manuell einen anderen Monat als aktuell setzt.</li>
                    <li>Offene Posten kannst du am Monatsende per „Übertragen“ in den nächsten Monat übernehmen.</li>
                </ul>
            </div>

            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Tipps für den Alltag</h3>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600 dark:text-slate-300">
                    <div class="rounded-xl border border-[var(--border)] bg-white/70 dark:bg-slate-900/60 p-4">
                        <div class="font-semibold text-gray-900 dark:text-slate-100">Kumuliert ab heute</div>
                        <div class="mt-1">Zeigt, was dir bis Monatsende realistisch bleibt.</div>
                    </div>
                    <div class="rounded-xl border border-[var(--border)] bg-white/70 dark:bg-slate-900/60 p-4">
                        <div class="font-semibold text-gray-900 dark:text-slate-100">Verrechnungskonten</div>
                        <div class="mt-1">Nutze sie für interne Umbuchungen, ohne das Ergebnis zu verfälschen.</div>
                    </div>
                    <div class="rounded-xl border border-[var(--border)] bg-white/70 dark:bg-slate-900/60 p-4">
                        <div class="font-semibold text-gray-900 dark:text-slate-100">Archiv</div>
                        <div class="mt-1">Vergangene Monate lassen sich sauber archivieren.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
