<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Monatsübersicht</h2>
            <a href="{{ route('months.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-md text-sm">Monat erstellen</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            @php
                $fmt = fn ($value) => new \Illuminate\Support\HtmlString(
                    number_format((float) $value, 2, '.', "'")
                );
            @endphp
            @if ($months->isEmpty())
                <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg p-6 accent-box border">
                    <p class="text-gray-600">Noch keine Monate vorhanden.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ($monthCards as $card)
                        @php
                            $month = $card['month'];
                            $metrics = $card['metrics'];
                            $result = $metrics['result'] ?? 0;
                            $resultClass = $result < 0 ? 'text-red-700 dark:text-red-200' : 'text-emerald-800 dark:text-emerald-200';
                            $resultBoxClass = $result < 0
                                ? 'border border-red-200/70 bg-red-100/80 dark:border-red-700/60 dark:bg-red-900/30'
                                : 'border border-emerald-200/70 bg-emerald-100/80 dark:border-emerald-700/60 dark:bg-emerald-900/30';
                            $openExpensesTotal = (float) ($metrics['open_expenses'] ?? 0) + (float) ($metrics['living_cost_open'] ?? 0);
                        @endphp
                        <a href="{{ route('months.show', $month) }}" class="group block rounded-xl border accent-box bg-white/80 dark:bg-slate-900/80 p-4 shadow-sm transition hover:shadow-md">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-[10px] uppercase tracking-[0.25em] text-gray-500">Monat</div>
                                    <div class="text-lg font-semibold text-gray-900 group-hover:text-gray-950 flex items-center gap-2">
                                        <span>{{ $month->name }}</span>
                                        @if ($month->is_current)
                                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800">Aktuell</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">{{ $month->date_from->format('d.m.Y') }} – {{ $month->date_to->format('d.m.Y') }}</div>
                                    <div class="text-xs text-gray-500">Lebensunterhalt/Tag CHF {{ $fmt($month->daily_living_cost) }}</div>
                                </div>
                                <div class="text-xs text-gray-400">→</div>
                            </div>

                            <div class="mt-4 rounded-lg px-3 py-2 text-center text-2xl font-semibold tabular-nums {{ $resultBoxClass }} {{ $resultClass }}">
                                CHF {{ $fmt($result) }}
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-gray-600">
                                <div class="flex items-center justify-between rounded-md bg-white/70 dark:bg-slate-900/60 px-2 py-1">
                                    <span>Einnahmen</span>
                                    <span class="font-semibold tabular-nums text-gray-900">CHF {{ $fmt($metrics['income_total'] ?? 0) }}</span>
                                </div>
                                <div class="flex items-center justify-between rounded-md bg-white/70 dark:bg-slate-900/60 px-2 py-1">
                                    <span>Ausgaben</span>
                                    <span class="font-semibold tabular-nums text-gray-900">CHF {{ $fmt($openExpensesTotal) }}</span>
                                </div>
                                <div class="flex items-center justify-between rounded-md bg-white/70 dark:bg-slate-900/60 px-2 py-1">
                                    <span>Arbeitstage</span>
                                    <span class="font-semibold tabular-nums text-gray-900">{{ $metrics['workdays_remaining'] ?? 0 }}</span>
                                </div>
                                <div class="flex items-center justify-between rounded-md bg-white/70 dark:bg-slate-900/60 px-2 py-1">
                                    <span>Umsatz/AT</span>
                                    <span class="font-semibold tabular-nums {{ $resultClass }}">CHF {{ $fmt($metrics['required_revenue_per_workday'] ?? 0) }}</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
