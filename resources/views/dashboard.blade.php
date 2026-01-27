<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">3-Monats-Übersicht</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('months.create') }}" class="inline-flex items-center px-3 py-1.5 bg-[var(--accent)] text-white rounded text-sm">Neuen Monat anlegen</a>
                <form method="POST" action="{{ route('months.next') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-gray-700 rounded text-sm">Nächsten Monat automatisch erstellen</button>
                </form>
            </div>
        </div>
    </x-slot>

    @php
        $fmt = fn ($value) => new \Illuminate\Support\HtmlString(
            number_format((float) $value, 2, '.', "'")
        );
    @endphp

    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            @if (! $hasMonths)
                <div class="border accent-box bg-white dark:bg-slate-900/80 p-6">
                    <p class="text-gray-600">Noch keine Monate vorhanden. Lege deinen ersten Monat an.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach ($monthBlocks as $block)
                        @if (! $block)
                            <div class="border border-dashed accent-box bg-white dark:bg-slate-900/80 p-4 text-sm text-gray-500">
                                <div class="text-xs uppercase tracking-wide text-gray-400">Monat</div>
                                <div class="text-lg font-semibold text-gray-500">Noch nicht angelegt</div>
                                <div class="mt-4">Lege den nächsten Monat an.</div>
                            </div>
                        @else
                            @php
                                $month = $block['month'];
                                $metrics = $block['metrics'];
                                $resultClass = $metrics['result'] < 0 ? 'text-red-700 dark:text-red-200' : 'text-green-800 dark:text-emerald-200';
                            @endphp
                            <a href="{{ route('months.show', $month) }}" class="block border accent-box bg-white dark:bg-slate-900/80 p-4 hover:bg-gray-50 dark:hover:bg-slate-900 transition">
                                <div class="text-xs uppercase tracking-wide text-gray-500">Monat</div>
                                <div class="text-lg font-semibold text-gray-900">{{ $month->name }}</div>
                                <div class="text-xs text-gray-500">{{ $month->date_from->format('d.m.Y') }} – {{ $month->date_to->format('d.m.Y') }}</div>

                                <div class="mt-4 space-y-2 text-sm tabular-nums">
                                    <div class="flex items-center justify-between">
                                        <span>Resultat</span>
                                        <span class="font-semibold {{ $resultClass }}">CHF {{ $fmt($metrics['result']) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>Arbeitstage</span>
                                        <span class="font-semibold">{{ $metrics['workdays_remaining'] }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>Umsatz / Arbeitstag</span>
                                        <span class="font-semibold">CHF {{ $fmt($metrics['required_revenue_per_workday']) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>Ab heute / Arbeitstag</span>
                                        <span class="font-semibold">CHF {{ $fmt($metrics['required_revenue_per_workday_from_today'] ?? 0) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>Einnahmen gesamt</span>
                                        <span class="font-semibold">CHF {{ $fmt($metrics['income_total'] ?? $metrics['open_forecast_income']) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>Offene Ausgaben</span>
                                        <span class="font-semibold">CHF {{ $fmt($metrics['open_expenses']) }}</span>
                                    </div>
                                </div>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
