<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Wiederkehrende Posten</h2>
            <a href="{{ route('recurring-templates.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-md text-sm">Neu</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            @php
                $fmt = fn ($value) => new \Illuminate\Support\HtmlString(
                    number_format((float) $value, 2, '.', "'")
                );
                $monthNames = [
                    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
                    7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
                ];
                $resolveMonths = function ($template) {
                    $months = $template->parsedMonthsMask();
                    if ($template->frequency === 'monthly') {
                        $months = range(1, 12);
                    } elseif ($template->frequency === 'quarterly') {
                        $months = $months ?: [1, 4, 7, 10];
                    } elseif ($template->frequency === 'yearly') {
                        $months = $months ?: [($template->created_at?->month ?? 1)];
                    }
                    $months = array_values(array_unique(array_map('intval', $months)));
                    sort($months);

                    return $months;
                };
                $incomeTemplates = $templates->where('kind', 'income');
                $expenseTemplates = $templates->whereIn('kind', ['fixcost', 'expense']);
                $year = now()->year;
                $monthTotals = [];
                for ($month = 1; $month <= 12; $month++) {
                    $monthTotals[$month] = ['income' => 0.0, 'expense' => 0.0];
                }
                foreach ($templates as $template) {
                    $months = $resolveMonths($template);
                    foreach ($months as $month) {
                        $monthStart = \Illuminate\Support\Carbon::create($year, $month, 1);
                        if ($template->ends_on && $template->ends_on->lt($monthStart)) {
                            continue;
                        }

                        if ($template->kind === 'income') {
                            $monthTotals[$month]['income'] += (float) $template->amount;
                        } else {
                            $monthTotals[$month]['expense'] += (float) $template->amount;
                        }
                    }
                }
                $overallIncome = 0.0;
                $overallExpense = 0.0;
                foreach ($monthTotals as $values) {
                    $overallIncome += $values['income'];
                    $overallExpense += $values['expense'];
                }
                $overallResult = $overallIncome - $overallExpense;
            @endphp

            @if ($templates->isEmpty())
                <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg p-6 border accent-box">
                    <p class="text-gray-600">Noch keine wiederkehrenden Posten vorhanden.</p>
                </div>
            @else
                <div class="space-y-6">
                    <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg p-6 border accent-box">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-slate-100">Monatliche Totale</h3>
                        <div class="overflow-x-auto mt-4">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500">
                                        <th class="py-2">Monat</th>
                                        <th class="text-right">Einnahmen</th>
                                        <th class="text-right">Ausgaben</th>
                                        <th class="text-right">Resultat</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach ($monthTotals as $month => $values)
                                        @php
                                            $income = round($values['income'], 2);
                                            $expense = round($values['expense'], 2);
                                            $result = round($income - $expense, 2);
                                        @endphp
                                        <tr>
                                            <td class="py-2">{{ $monthNames[$month] ?? $month }}</td>
                                        <td class="text-right tabular-nums font-semibold">CHF {{ $fmt($income) }}</td>
                                        <td class="text-right tabular-nums font-semibold">CHF {{ $fmt($expense) }}</td>
                                        <td class="text-right tabular-nums font-semibold">CHF {{ $fmt($result) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t">
                                    <tr class="font-semibold">
                                        <td class="py-2">Gesamt</td>
                                        <td class="text-right tabular-nums font-semibold">CHF {{ $fmt($overallIncome) }}</td>
                                        <td class="text-right tabular-nums font-semibold">CHF {{ $fmt($overallExpense) }}</td>
                                        <td class="text-right tabular-nums font-semibold">CHF {{ $fmt($overallResult) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg p-6 border accent-box" x-data="{ query: '' }">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-slate-100">Einnahmen</h3>
                            <input type="text" x-model="query" class="border-gray-300 rounded-md shadow-sm text-sm" placeholder="Suchen...">
                        </div>
                        <div class="overflow-x-auto mt-4">
                            <table class="min-w-full text-sm" data-sort-table>
                                <thead>
                                    <tr class="text-left text-gray-500">
                                        <th class="py-2">
                                            <button type="button" class="inline-flex items-center gap-1 text-gray-500" data-sort-key="name" data-sort-type="text">
                                                Name <span class="text-[10px]" data-sort-indicator></span>
                                            </button>
                                        </th>
                                        <th class="text-right">
                                            <button type="button" class="inline-flex items-center gap-1 text-gray-500 justify-end w-full" data-sort-key="amount" data-sort-type="number">
                                                Betrag <span class="text-[10px]" data-sort-indicator></span>
                                            </button>
                                        </th>
                                        <th>
                                            <button type="button" class="inline-flex items-center gap-1 text-gray-500" data-sort-key="months" data-sort-type="text">
                                                Monate <span class="text-[10px]" data-sort-indicator></span>
                                            </button>
                                        </th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y" data-sortable data-order-url="{{ route('recurring-templates.order') }}" data-kind="income">
                                    @forelse ($incomeTemplates as $template)
                                        @php
                                            $months = $resolveMonths($template);
                                            $monthsLabel = count($months) === 12
                                                ? 'Monatlich'
                                                : collect($months)->map(fn ($month) => $monthNames[$month] ?? $month)->implode(', ');
                                            $searchValue = strtolower($template->name);
                                        @endphp
                                        <tr class="cursor-move" draggable="true" data-template-id="{{ $template->id }}" data-search="{{ $searchValue }}" data-sort-row data-sort-name="{{ $searchValue }}" data-sort-amount="{{ $template->amount }}" data-sort-months="{{ strtolower($monthsLabel) }}" x-show="!query || $el.dataset.search.includes(query.toLowerCase())">
                                            <td class="py-2">{{ $template->name }}</td>
                                            <td class="text-right tabular-nums font-semibold">CHF {{ $fmt($template->amount) }}</td>
                                            <td class="text-gray-600">{{ $monthsLabel ?: '–' }}</td>
                                            <td class="text-right">
                                                <a href="{{ route('recurring-templates.edit', $template) }}" class="inline-flex items-center accent-icon hover:opacity-80" title="Bearbeiten" aria-label="Bearbeiten">
                                                    <x-icon-edit class="w-4 h-4" />
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="py-3 text-gray-500">Keine wiederkehrenden Einnahmen vorhanden.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg p-6 border accent-box" x-data="{ query: '' }">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-slate-100">Ausgaben</h3>
                            <input type="text" x-model="query" class="border-gray-300 rounded-md shadow-sm text-sm" placeholder="Suchen...">
                        </div>
                        <div class="overflow-x-auto mt-4">
                            <table class="min-w-full text-sm" data-sort-table>
                                <thead>
                                    <tr class="text-left text-gray-500">
                                        <th class="py-2">
                                            <button type="button" class="inline-flex items-center gap-1 text-gray-500" data-sort-key="name" data-sort-type="text">
                                                Name <span class="text-[10px]" data-sort-indicator></span>
                                            </button>
                                        </th>
                                        <th class="text-right">
                                            <button type="button" class="inline-flex items-center gap-1 text-gray-500 justify-end w-full" data-sort-key="amount" data-sort-type="number">
                                                Betrag <span class="text-[10px]" data-sort-indicator></span>
                                            </button>
                                        </th>
                                        <th class="text-right">
                                            <button type="button" class="inline-flex items-center gap-1 text-gray-500 justify-end w-full" data-sort-key="remaining" data-sort-type="number">
                                                Restbetrag <span class="text-[10px]" data-sort-indicator></span>
                                            </button>
                                        </th>
                                        <th>
                                            <button type="button" class="inline-flex items-center gap-1 text-gray-500" data-sort-key="end" data-sort-type="text">
                                                Ende <span class="text-[10px]" data-sort-indicator></span>
                                            </button>
                                        </th>
                                        <th>
                                            <button type="button" class="inline-flex items-center gap-1 text-gray-500" data-sort-key="months" data-sort-type="text">
                                                Monate <span class="text-[10px]" data-sort-indicator></span>
                                            </button>
                                        </th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y" data-sortable data-order-url="{{ route('recurring-templates.order') }}" data-kind="fixcost">
                                    @forelse ($expenseTemplates as $template)
                                        @php
                                            $months = $resolveMonths($template);
                                            $monthsLabel = count($months) === 12
                                                ? 'Monatlich'
                                                : collect($months)->map(fn ($month) => $monthNames[$month] ?? $month)->implode(', ');
                                            $searchValue = strtolower($template->name);
                                            $remainingValue = $template->remaining_amount !== null ? (float) $template->remaining_amount : 0;
                                            $endValue = $template->ends_on?->format('Y-m-d') ?? '';
                                        @endphp
                                        <tr class="cursor-move" draggable="true" data-template-id="{{ $template->id }}" data-search="{{ $searchValue }}" data-sort-row data-sort-name="{{ $searchValue }}" data-sort-amount="{{ $template->amount }}" data-sort-remaining="{{ $remainingValue }}" data-sort-end="{{ $endValue }}" data-sort-months="{{ strtolower($monthsLabel) }}" x-show="!query || $el.dataset.search.includes(query.toLowerCase())">
                                            <td class="py-2">{{ $template->name }}</td>
                                            <td class="text-right tabular-nums font-semibold">CHF {{ $fmt($template->amount) }}</td>
                                            <td class="text-right">
                                                @if ($template->remaining_amount !== null)
                                                    <span class="tabular-nums font-semibold">CHF {{ $fmt($template->remaining_amount) }}</span>
                                                @else
                                                    –
                                                @endif
                                            </td>
                                            <td>
                                                @if ($template->ends_on)
                                                    {{ $template->ends_on->format('d.m.Y') }}
                                                @else
                                                    –
                                                @endif
                                            </td>
                                            <td class="text-gray-600">{{ $monthsLabel ?: '–' }}</td>
                                            <td class="text-right">
                                                <a href="{{ route('recurring-templates.edit', $template) }}" class="inline-flex items-center accent-icon hover:opacity-80" title="Bearbeiten" aria-label="Bearbeiten">
                                                    <x-icon-edit class="w-4 h-4" />
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-3 text-gray-500">Keine wiederkehrenden Fixkosten vorhanden.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            const setupSortable = (tbody) => {
                let draggingRow = null;
                const orderUrl = tbody.dataset.orderUrl;
                const kind = tbody.dataset.kind;

                const persistOrder = () => {
                    const ids = Array.from(tbody.querySelectorAll('[data-template-id]')).map((row) => row.dataset.templateId);
                    if (!ids.length || !orderUrl || !csrfToken) {
                        return;
                    }

                    fetch(orderUrl, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ template_ids: ids, kind }),
                    }).catch(() => {});
                };

                tbody.addEventListener('dragstart', (event) => {
                    const row = event.target.closest('[data-template-id]');
                    if (!row) {
                        return;
                    }

                    draggingRow = row;
                    row.classList.add('opacity-50');
                    event.dataTransfer.effectAllowed = 'move';
                });

                tbody.addEventListener('dragend', () => {
                    if (draggingRow) {
                        draggingRow.classList.remove('opacity-50');
                    }
                    draggingRow = null;
                });

                tbody.addEventListener('dragover', (event) => {
                    if (!draggingRow) {
                        return;
                    }
                    event.preventDefault();

                    const row = event.target.closest('[data-template-id]');
                    if (!row || row === draggingRow) {
                        return;
                    }

                    const rect = row.getBoundingClientRect();
                    const after = (event.clientY - rect.top) > rect.height / 2;
                    if (after) {
                        row.after(draggingRow);
                    } else {
                        row.before(draggingRow);
                    }
                });

                tbody.addEventListener('drop', (event) => {
                    if (!draggingRow) {
                        return;
                    }
                    event.preventDefault();
                    persistOrder();
                });
            };

            document.querySelectorAll('[data-sortable]').forEach(setupSortable);

            const setupHeaderSort = (table) => {
                const tbody = table.querySelector('tbody');
                const headers = table.querySelectorAll('[data-sort-key]');

                headers.forEach((header) => {
                    header.addEventListener('click', () => {
                        const key = header.dataset.sortKey;
                        const type = header.dataset.sortType || 'text';
                        const rows = Array.from(tbody.querySelectorAll('[data-sort-row]'));
                        if (!rows.length) {
                            return;
                        }

                        const direction = header.dataset.sortDir === 'asc' ? 'desc' : 'asc';
                        headers.forEach((item) => {
                            if (item !== header) {
                                item.dataset.sortDir = '';
                                const indicator = item.querySelector('[data-sort-indicator]');
                                if (indicator) {
                                    indicator.textContent = '';
                                }
                            }
                        });
                        header.dataset.sortDir = direction;
                        const indicator = header.querySelector('[data-sort-indicator]');
                        if (indicator) {
                            indicator.textContent = direction === 'asc' ? '▲' : '▼';
                        }

                        const datasetKey = `sort${key.charAt(0).toUpperCase()}${key.slice(1)}`;
                        rows.sort((a, b) => {
                            let av = a.dataset[datasetKey] ?? '';
                            let bv = b.dataset[datasetKey] ?? '';

                            if (type === 'number') {
                                av = parseFloat(av) || 0;
                                bv = parseFloat(bv) || 0;
                            } else {
                                av = av.toLowerCase();
                                bv = bv.toLowerCase();
                            }

                            if (av < bv) {
                                return direction === 'asc' ? -1 : 1;
                            }
                            if (av > bv) {
                                return direction === 'asc' ? 1 : -1;
                            }
                            return 0;
                        });

                        rows.forEach((row) => tbody.appendChild(row));
                    });
                });
            };

            document.querySelectorAll('[data-sort-table]').forEach(setupHeaderSort);
        });
    </script>
</x-app-layout>
