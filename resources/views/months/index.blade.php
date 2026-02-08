<x-app-layout :mobile-title="'Monatsübersicht'">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Monatsübersicht</h2>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            @php
                $fmt = fn ($value) => new \Illuminate\Support\HtmlString(
                    number_format((float) $value, 2, '.', "'")
                );
            @endphp
            @if ($months->isEmpty())
                <div
                    x-data="{ show: localStorage.getItem('onboardingDismissed') !== '1' }"
                    x-init="$watch('show', value => value === false && localStorage.setItem('onboardingDismissed','1'))"
                    x-show="show"
                    class="mb-4 border border-emerald-200/70 bg-emerald-50/80 text-emerald-900 p-4 rounded-2xl accent-box"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-2 text-sm">
                            <div class="text-[11px] uppercase tracking-[0.25em] text-emerald-700">Start hier</div>
                            <div class="font-semibold">Lege zuerst deinen ersten Monat an – danach Konten und Einträge.</div>
                            <div class="text-emerald-800/90">Schritte: Monat anlegen → Konten erfassen → Einnahmen & Ausgaben eintragen → optional wiederkehrende Posten.</div>
                        </div>
                        <button type="button" class="text-xs font-semibold underline underline-offset-4" @click="show = false">Ausblenden</button>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg p-6 accent-box border rounded-2xl">
                    <p class="text-gray-600">Noch keine Monate vorhanden. Starte mit „Monat erstellen“, um die Planung zu beginnen.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ($monthCards as $card)
                        @php
                            $month = $card['month'];
                            $metrics = $card['metrics'];
                            $cumulative = $card['cumulative'] ?? [];
                            $holidays = $card['holidays'] ?? collect();
                            $holidayCount = $holidays->count();
                            $holidayPreview = $holidays->take(2)->map(function ($holiday) {
                                $label = $holiday->name ?: 'Ferien';
                                $range = $holiday->date_from->format('d.m') . '–' . $holiday->date_to->format('d.m');
                                return $label . ' ' . $range;
                            })->implode(' · ');
                            $holidayWorkdaysDeducted = (int) ($metrics['holiday_workdays_deducted'] ?? 0);
                            $result = $metrics['result'] ?? 0;
                            $resultClass = $result < 0 ? 'text-red-700 dark:text-red-200' : 'text-emerald-800 dark:text-emerald-200';
                            $resultBoxClass = $result < 0
                                ? 'border border-red-200/70 bg-red-100/80 dark:border-red-700/60 dark:bg-red-900/30'
                                : 'border border-emerald-200/70 bg-emerald-100/80 dark:border-emerald-700/60 dark:bg-emerald-900/30';
                            $openExpensesTotal = (float) ($metrics['open_expenses'] ?? 0) + (float) ($metrics['living_cost_open'] ?? 0);
                            $cumulativeResult = $cumulative['result_sum'] ?? 0;
                            $cumulativeClass = $cumulativeResult < 0 ? 'text-red-700 dark:text-red-200' : 'text-emerald-800 dark:text-emerald-200';
                        @endphp
                        <a href="{{ route('months.show', $month) }}" class="group block rounded-2xl border accent-box bg-white/85 dark:bg-slate-900/85 p-5 shadow-sm transition hover:shadow-md hover:-translate-y-0.5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-[10px] uppercase tracking-[0.25em] text-gray-500 dark:text-slate-400">Monat</div>
                                    <div class="text-xl font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-2">
                                        <span>{{ $month->name }}</span>
                                        @if ($month->is_current)
                                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:border-emerald-700/60 dark:bg-emerald-900/30 dark:text-emerald-200">Aktuell</span>
                                        @endif
                                        @if ($month->archived_at)
                                            <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600 dark:border-slate-600/60 dark:bg-slate-800/60 dark:text-slate-300">Archiviert</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-slate-400">{{ $month->date_from->format('d.m.Y') }} – {{ $month->date_to->format('d.m.Y') }}</div>
                                    <div class="text-xs text-gray-500 dark:text-slate-400">Lebensunterhalt/Tag CHF {{ $fmt($month->daily_living_cost) }}</div>
                                    @if ($holidayCount > 0)
                                        <div class="mt-1 text-[11px] text-blue-700/90 space-y-0.5">
                                            Ferien: {{ $holidayPreview }}
                                            @if ($holidayCount > 2)
                                                <span class="text-blue-600">+{{ $holidayCount - 2 }} weitere</span>
                                            @endif
                                            @if ($isSelfEmployed && $holidayWorkdaysDeducted > 0)
                                                <div class="text-[10px] text-gray-500">Abgezogene Arbeitstage: {{ $holidayWorkdaysDeducted }}</div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-400 dark:text-slate-500">→</div>
                            </div>

                            <div class="mt-4">
                                <div class="flex items-center gap-2 text-[10px] uppercase tracking-[0.25em] text-emerald-700/90">
                                    <span>Monatsergebnis</span>
                                    <x-info-tooltip text="Einnahmen minus offene Ausgaben/Fixkosten dieses Monats. Kontostände zählen nur im aktuellen Monat." />
                                </div>
                                <div class="mt-2 rounded-2xl px-3 py-3 text-center text-2xl font-semibold tabular-nums {{ $resultBoxClass }} {{ $resultClass }}">
                                    CHF {{ $fmt($result) }}
                                </div>
                            </div>

                            <div class="mt-3">
                                <div class="flex items-center gap-2 text-[10px] uppercase tracking-[0.25em] text-gray-500 dark:text-slate-400">
                                    <span>Kummuliert ab heute</span>
                                    <x-info-tooltip text="Zeigt, was ab heute bis Monatsende übrig bleibt (Einnahmen minus Ausgaben/Fixkosten ab heute)." />
                                </div>
                                <div class="mt-2 grid grid-cols-1 {{ $isSelfEmployed ? 'sm:grid-cols-3' : '' }} gap-2 text-xs text-gray-600 dark:text-slate-300">
                                    <div class="flex items-center justify-between rounded-md bg-white/70 dark:bg-slate-900/60 px-2 py-1">
                                        <span class="flex items-center gap-2">
                                            Resultat
                                            <x-info-tooltip text="Kumuliertes Ergebnis ab heute." />
                                        </span>
                                        <span class="font-semibold tabular-nums {{ $cumulativeClass }}">CHF {{ $fmt($cumulativeResult) }}</span>
                                    </div>
                                    @if ($isSelfEmployed)
                                        <div class="flex items-center justify-between rounded-md bg-white/70 dark:bg-slate-900/60 px-2 py-1">
                                            <span class="flex items-center gap-2">
                                                Arbeitstage
                                                <x-info-tooltip text="Verbleibende Arbeitstage ab heute." />
                                            </span>
                                            <span class="font-semibold tabular-nums text-gray-900">{{ $cumulative['workdays_sum'] ?? 0 }}</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-md bg-white/70 dark:bg-slate-900/60 px-2 py-1">
                                            <span class="flex items-center gap-2">
                                                Umsatz/AT
                                                <x-info-tooltip text="Erforderlicher Umsatz pro Arbeitstag ab heute." />
                                            </span>
                                            <span class="font-semibold tabular-nums {{ $cumulativeClass }}">CHF {{ $fmt($cumulative['required_per_workday'] ?? 0) }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-gray-600 dark:text-slate-300">
                                <div class="flex items-center justify-between rounded-md bg-white/70 dark:bg-slate-900/60 px-2 py-1">
                                    <span class="flex items-center gap-2">
                                        Einnahmen
                                        <x-info-tooltip text="Offene Einnahmen im Monat." />
                                    </span>
                                    <span class="font-semibold tabular-nums text-gray-900 dark:text-slate-100">CHF {{ $fmt($metrics['income_total'] ?? 0) }}</span>
                                </div>
                                <div class="flex items-center justify-between rounded-md bg-white/70 dark:bg-slate-900/60 px-2 py-1">
                                    <span class="flex items-center gap-2">
                                        Ausgaben
                                        <x-info-tooltip text="Offene Ausgaben plus Lebensunterhalt." />
                                    </span>
                                    <span class="font-semibold tabular-nums text-gray-900 dark:text-slate-100">CHF {{ $fmt($openExpensesTotal) }}</span>
                                </div>
                                @if ($isSelfEmployed)
                                    <div class="flex items-center justify-between rounded-md bg-white/70 dark:bg-slate-900/60 px-2 py-1">
                                        <span class="flex items-center gap-2">
                                            Arbeitstage
                                            <x-info-tooltip text="Verbleibende Arbeitstage im Monat." />
                                        </span>
                                        <span class="font-semibold tabular-nums text-gray-900 dark:text-slate-100">{{ $metrics['workdays_remaining'] ?? 0 }}</span>
                                    </div>
                                    <div class="flex items-center justify-between rounded-md bg-white/70 dark:bg-slate-900/60 px-2 py-1">
                                        <span class="flex items-center gap-2">
                                            Umsatz/AT
                                            <x-info-tooltip text="Erforderlicher Umsatz pro verbleibendem Arbeitstag." />
                                        </span>
                                        <span class="font-semibold tabular-nums {{ $resultClass }}">CHF {{ $fmt($metrics['required_revenue_per_workday'] ?? 0) }}</span>
                                    </div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-2 sm:max-w-xl">
                <a href="{{ route('months.create') }}" class="touch-target inline-flex items-center justify-center rounded-2xl bg-[var(--accent)] text-white text-base font-semibold">Monat erstellen</a>
                <a href="{{ route('months.index', $showArchived ? [] : ['show_archived' => 1]) }}" class="touch-target inline-flex items-center justify-center rounded-2xl border border-[var(--border)] bg-white/80 text-base font-semibold text-gray-700 dark:text-slate-100">
                    {{ $showArchived ? 'Archivierte ausblenden' : 'Archivierte anzeigen' }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
