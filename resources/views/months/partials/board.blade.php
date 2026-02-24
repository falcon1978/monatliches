@php
    $fmt = fn ($value) => new \Illuminate\Support\HtmlString(
        number_format((float) $value, 2, '.', "'")
    );
    $json = fn ($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $today = now()->startOfDay();
    $pendingTemplates = $pendingTemplates ?? 0;
    $showMonthHeader = $showMonthHeader ?? false;
    $isCompact = $isCompact ?? false;
    $actionStackClass = 'flex flex-col items-end gap-1';
    $actionRowClass = 'flex items-center justify-end gap-2 leading-tight whitespace-nowrap';
    $rowPadClass = $isCompact ? 'py-2' : 'py-1';
    $inputStackClass = $isCompact ? 'flex flex-col gap-1.5' : 'flex items-center gap-2';
    $editActionBase = 'inline-flex items-center justify-center px-2 py-1 rounded text-xs font-semibold';
    $editActionPrimary = $editActionBase.' bg-[var(--accent)] text-white';
    $editActionGhost = $editActionBase.' border border-gray-300 text-gray-600';
    $editActionDanger = $editActionBase.' border border-red-200 text-red-700';
    $livingCostBase = (float) ($metrics['living_cost_base'] ?? $metrics['living_cost_open'] ?? 0);
    $holidayCustomLivingCost = (float) ($metrics['holiday_custom_living_cost'] ?? 0);
    $holidayDeductedDays = (int) ($metrics['holiday_deducted_days'] ?? 0);
    $holidayWorkdaysDeducted = (int) ($metrics['holiday_workdays_deducted'] ?? 0);
    $nextMonthLivingCost = (float) ($metrics['next_month_living_cost'] ?? 0);
    $nextMonthLivingCostBase = (float) ($metrics['next_month_living_cost_base'] ?? 0);
    $nextMonthHolidayCustomLivingCost = (float) ($metrics['next_month_holiday_custom_living_cost'] ?? 0);
    $nextMonthLivingCostFromToday = (bool) ($metrics['next_month_living_cost_from_today'] ?? false);
    $includeCurrentLivingCost = (bool) ($metrics['include_current_living_cost'] ?? true);
    $livingLabel = $includeCurrentLivingCost
        ? 'Lebensunterhalt ab Heute'
        : 'Lebensunterhalt für diesen Monat';
    $holidays = $holidays ?? collect();
    $nextMonthHolidays = $nextMonthHolidays ?? collect();
    $holidayModeLabels = [
        'deduct' => 'Lebensunterhalt abziehen',
        'keep' => 'Lebensunterhalt belassen',
        'custom' => 'Benutzerdefiniert',
    ];

    $forecastAccounts = $accounts->whereIn('type', ['forecast', 'clearing']);
    $balanceAccounts = $accounts->where('type', 'ist');
    $istAccounts = $accounts->where('type', 'ist');
    $payAccounts = $accounts->whereIn('type', ['ist', 'clearing']);
    $defaultIstAccount = $istAccounts->first();
    $defaultForecastAccount = $forecastAccounts->first();
    $balanceAmounts = $accountBalances ?? collect();
    $balanceMeta = $balanceMeta ?? [];
    $forecastBalances = $forecastBalances ?? collect();
    $includeBalanceInResult = (bool) ($metrics['include_balance_in_result'] ?? false);

    $incomes = $entries
        ->where('type', 'income')
        ->whereIn('status', ['open', 'partial'])
        ->values();
    $resolveIncomeSource = static function ($entry): string {
        if ($entry->income_source !== null) {
            return $entry->income_source;
        }

        if ($entry->recurring_template_id) {
            return 'manual';
        }

        return in_array($entry->account?->type, ['forecast', 'clearing'], true) ? 'expected' : 'manual';
    };
    $recurringIncomes = $incomes
        ->whereNotNull('recurring_template_id')
        ->filter(fn ($entry) => $resolveIncomeSource($entry) === 'manual')
        ->values();
    $manualIncomes = $incomes
        ->whereNull('recurring_template_id')
        ->filter(fn ($entry) => $resolveIncomeSource($entry) === 'manual')
        ->values();
    $customerIncomes = $incomes
        ->filter(fn ($entry) => $resolveIncomeSource($entry) === 'expected' && in_array($entry->status, ['open', 'partial'], true))
        ->values();
    $customerIncomeSum = $customerIncomes->sum(fn ($entry) => $entry->open_amount);
    $recurringIncomeSum = $recurringIncomes->sum(fn ($entry) => $entry->open_amount);
    $manualIncomeSum = $manualIncomes->sum(fn ($entry) => $entry->open_amount);
    $balanceSum = $balanceAccounts->sum(fn ($account) => (float) ($balanceAmounts[$account->id] ?? 0));
    $incomeSum = $customerIncomeSum + $recurringIncomeSum + $manualIncomeSum;

    $expenses = $entries
        ->where('type', 'expense')
        ->where('status', '!=', 'paid')
        ->values();
    $expenseSum = $expenses->sum('amount');

    $fixcosts = $entries
        ->where('type', 'fixcost')
        ->where('status', '!=', 'paid')
        ->values();
    $fixcostOpenSum = $fixcosts->sum(fn ($entry) => $entry->status === 'paid' ? 0 : (float) $entry->amount)
        + (float) $metrics['living_cost_open'];

    $resultIsNegative = ($metrics['cumulative_result'] ?? 0) < 0;
    $monthResultIsNegative = ($metrics['result'] ?? 0) < 0;
    $resultTextClass = $resultIsNegative ? 'text-red-700 dark:text-red-200' : 'text-green-800 dark:text-emerald-200';
    $monthResultClass = $monthResultIsNegative
        ? 'border border-red-200/70 bg-red-100 text-red-800 dark:border-red-700/60 dark:bg-red-900/30 dark:text-red-200'
        : 'border border-emerald-200/70 bg-emerald-100 text-emerald-900 dark:border-emerald-700/60 dark:bg-emerald-900/30 dark:text-emerald-200';
    $monthResultBarClass = $monthResultIsNegative
        ? 'bg-gradient-to-r from-red-200 via-red-100 to-red-200 text-red-800 border border-red-200/70 dark:from-red-900/40 dark:via-red-900/20 dark:to-red-900/40 dark:text-red-200 dark:border-red-700/60'
        : 'bg-gradient-to-r from-emerald-200 via-emerald-100 to-emerald-200 text-emerald-900 border border-emerald-200/70 dark:from-emerald-900/40 dark:via-emerald-900/20 dark:to-emerald-900/40 dark:text-emerald-200 dark:border-emerald-700/60';
    $resultBarClass = $resultIsNegative
        ? 'bg-gradient-to-r from-red-200 via-red-100 to-red-200 text-red-800 border border-red-200/70 dark:from-red-900/40 dark:via-red-900/20 dark:to-red-900/40 dark:text-red-200 dark:border-red-700/60'
        : 'bg-gradient-to-r from-emerald-200 via-emerald-100 to-emerald-200 text-emerald-900 border border-emerald-200/70 dark:from-emerald-900/40 dark:via-emerald-900/20 dark:to-emerald-900/40 dark:text-emerald-200 dark:border-emerald-700/60';
    $hideWorkdayMetrics = auth()->user()?->employment_type === 'employed';
    $statusLabels = ['open' => 'Offen', 'partial' => 'Teilbezahlt', 'paid' => 'Bezahlt'];
    $statusClasses = [
        'open' => 'border border-amber-200/70 bg-amber-100/80 text-amber-900 dark:border-amber-700/60 dark:bg-amber-900/30 dark:text-amber-100',
        'partial' => 'border border-blue-200/70 bg-blue-100/80 text-blue-900 dark:border-blue-700/60 dark:bg-blue-900/30 dark:text-blue-100',
        'paid' => 'border border-emerald-200/70 bg-emerald-100/80 text-emerald-900 dark:border-emerald-700/60 dark:bg-emerald-900/30 dark:text-emerald-100',
    ];
    $incomePaymentMap = $incomes->mapWithKeys(fn ($entry) => [$entry->id => (float) $entry->open_amount]);
    $firstIncomeId = $incomes->first()?->id;
    $payableEntries = $expenses->concat($fixcosts);
    $payableActionMap = $payableEntries->mapWithKeys(fn ($entry) => [$entry->id => route('entries.pay', $entry)]);
    $firstPayableId = $payableEntries->first()?->id;
@endphp

<div
    class="space-y-4"
    x-data="{
        entriesOpen: {{ ($entriesOpen ?? false) ? 'true' : 'false' }},
        editing: false,
        sheet: {{ $json(request()->boolean('quick_add') ? 'quick' : null) }},
        payAction: null,
        payLabel: '',
        paymentEntryId: null,
        paymentAmount: null,
        incomePaymentMap: {{ $json($incomePaymentMap) }},
        payableEntryId: {{ $json($firstPayableId) }},
        payableActionMap: {{ $json($payableActionMap) }},
    }"
    x-init="if (entriesOpen) { $nextTick(() => $refs.entriesSection?.scrollIntoView({ behavior: 'smooth', block: 'start' })) }"
    x-on:open-quick-add.window="sheet = 'quick'"
>

    @if ($pendingTemplates > 0)
        <div class="border border-amber-200 bg-amber-50 text-amber-900 p-3 text-sm flex flex-wrap items-center justify-between gap-3 accent-box">
            <div>Für diesen Monat sind {{ $pendingTemplates }} neue wiederkehrende Posten verfügbar.</div>
            <form method="POST" action="{{ route('months.import-templates', $month) }}">
                @csrf
                <button type="submit" class="touch-target px-4 py-2 bg-[var(--accent)] text-white rounded-xl text-sm font-semibold">Übernehmen</button>
            </form>
        </div>
    @endif

    @if (($prevMonthOpenCount ?? 0) > 0 && $month->is_current)
        <div class="border border-amber-200 bg-amber-50 text-amber-900 p-3 text-sm accent-box">
            Im Vormonat {{ $prevMonth?->name }} sind noch {{ $prevMonthOpenCount }} offene Posten. Der Übertrag in den nächsten Monat ist gesperrt.
        </div>
    @endif

    @if ($holidays->isNotEmpty() || $nextMonthHolidays->isNotEmpty())
        @php
            $holidayCards = collect();
            foreach ($holidays as $holiday) {
                $holidayCards->push(['holiday' => $holiday, 'is_next' => false]);
            }
            foreach ($nextMonthHolidays as $holiday) {
                $holidayCards->push(['holiday' => $holiday, 'is_next' => true]);
            }
        @endphp
        <div class="space-y-2">
            @if (! $hideWorkdayMetrics && $holidayWorkdaysDeducted > 0)
                <div class="text-xs text-gray-500">Abgezogene Arbeitstage (Monat): {{ $holidayWorkdaysDeducted }}</div>
            @endif
            @foreach ($holidayCards as $card)
                @php
                    $holiday = $card['holiday'];
                    $mode = $holiday->living_cost_mode ?? 'deduct';
                    $label = $holidayModeLabels[$mode] ?? $mode;
                    $customLabel = $mode === 'custom' && $holiday->custom_living_cost !== null
                        ? 'CHF '.number_format((float) $holiday->custom_living_cost, 2, '.', "'").'/Tag'
                        : null;
                @endphp
                <a href="{{ route('holidays.edit', $holiday) }}" class="group block rounded-lg border border-blue-200/70 bg-blue-50/70 dark:bg-slate-900/60 px-3 py-2 text-sm transition hover:shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-gray-900 dark:text-slate-100">{{ $holiday->name ?: 'Ferien' }}</div>
                            <div class="text-xs text-gray-600">
                                {{ $holiday->date_from->format('d.m.Y') }} – {{ $holiday->date_to->format('d.m.Y') }}
                                @if ($card['is_next'] && ! empty($nextMonth?->name))
                                    <span class="text-[10px] text-gray-500">({{ $nextMonth->name }})</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-700 sm:text-right">
                            <div class="text-right">
                                <div>{{ $label }}</div>
                                @if ($customLabel)
                                    <div class="text-[11px] text-gray-500">{{ $customLabel }}</div>
                                @endif
                            </div>
                            <span class="text-[var(--accent)] opacity-70 group-hover:opacity-100" aria-hidden="true">
                                <x-icon-edit class="h-4 w-4" />
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @if ($balanceAccounts->isNotEmpty())
        <div class="rounded-xl border accent-box bg-white/80 dark:bg-slate-900/70 shadow-sm">
            <div class="p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-gray-500">
                        <span>Kontostände</span>
                        <x-info-tooltip text="Kontostände kannst du jederzeit anpassen. Sie werden in jedem Monat angezeigt, ins Ergebnis zählt aber nur der aktuelle Monat." />
                    </div>
                    <div class="text-sm font-semibold text-gray-800 dark:text-slate-100">Summe CHF {{ $fmt($balanceSum) }}</div>
                </div>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 w-full">
                    @foreach ($balanceAccounts as $account)
                        @php
                            $balance = $balanceAmounts[$account->id] ?? 0;
                            $balanceClass = $balance < 0 ? 'text-red-700' : 'text-gray-900';
                            $balanceInput = number_format((float) $balance, 2, '.', '');
                            $balanceDisplay = number_format((float) $balance, 2, '.', "'");
                        @endphp
                        <div
                            class="w-full rounded-lg border accent-box bg-white/80 dark:bg-slate-900/70 px-3 py-2 transition hover:shadow-sm cursor-pointer"
                            x-data="balanceEditor({{ $json($balanceInput) }}, {{ $json($balanceDisplay) }})"
                            @click="if (!editing) { focusInput() }"
                            @keydown.enter.prevent="if (!editing) { focusInput() }"
                            @keydown.space.prevent="if (!editing) { focusInput() }"
                            @click.outside="if (editing) { cancel() }"
                            role="button"
                            tabindex="0"
                            :class="editing ? 'ring-1 ring-[var(--accent)]/40' : ''"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="text-xs uppercase tracking-wide text-gray-500">{{ $account->name }}</div>
                                <x-info-tooltip text="Kontostand hier jederzeit anpassen – ideal, wenn du kleine Ausgaben nicht einzeln erfassen willst." />
                            </div>
                            <form method="POST" action="{{ route('months.balances.update', [$month, $account]) }}" class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center" @submit="if (!prepareSubmit()) { $event.preventDefault() }">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="amount" x-model="value">
                                <div class="w-full flex-1">
                                    <div x-show="!editing" class="flex items-baseline justify-end gap-2">
                                        <span class="text-[10px] uppercase tracking-[0.2em] text-gray-400">CHF</span>
                                        <span class="text-lg font-semibold tabular-nums {{ $balanceClass }}">{{ $fmt($balance) }}</span>
                                    </div>
                                    <div x-show="editing" x-cloak class="space-y-2 rounded-xl border border-[var(--border)] bg-white/90 dark:bg-slate-900/80 px-3 py-2 shadow-inner">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="grid w-full grid-cols-2 rounded-full bg-slate-200/80 p-0.5 shadow-inner dark:bg-slate-800/90 sm:inline-flex sm:w-auto sm:items-center">
                                                <button
                                                    type="button"
                                                    @click="setEditMode('delta')"
                                                    :aria-pressed="editMode === 'delta' ? 'true' : 'false'"
                                                    class="h-8 w-full rounded-full px-3 text-[11px] font-semibold uppercase tracking-[0.15em] transition-all duration-150 sm:h-7"
                                                    :class="editMode === 'delta' ? 'bg-[var(--accent)] text-white shadow-sm ring-1 ring-[var(--accent)]/40' : 'text-gray-600 dark:text-slate-300'"
                                                >Rechnen</button>
                                                <button
                                                    type="button"
                                                    @click="setEditMode('absolute')"
                                                    :aria-pressed="editMode === 'absolute' ? 'true' : 'false'"
                                                    class="h-8 w-full rounded-full px-3 text-[11px] font-semibold uppercase tracking-[0.15em] transition-all duration-150 sm:h-7"
                                                    :class="editMode === 'absolute' ? 'bg-[var(--accent)] text-white shadow-sm ring-1 ring-[var(--accent)]/40' : 'text-gray-600 dark:text-slate-300'"
                                                >Direkt</button>
                                            </div>
                                            <div class="text-[10px] font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-slate-400 sm:text-right" x-text="editMode === 'delta' ? 'Modus Rechnen' : 'Modus Direkt'"></div>
                                        </div>

                                        <div x-show="editMode === 'delta'" x-cloak class="space-y-1">
                                            <div class="flex items-center justify-end gap-2">
                                                <span class="text-[10px] uppercase tracking-[0.2em] text-gray-400">CHF</span>
                                                <span class="text-base font-semibold tabular-nums text-gray-900 dark:text-slate-100" x-text="baseDisplay"></span>
                                            </div>
                                            <div class="flex items-center justify-end gap-2">
                                                <div class="inline-flex items-center rounded-full bg-slate-200/80 dark:bg-slate-800/90 p-0.5 shadow-inner">
                                                    <button
                                                        type="button"
                                                        @click="operator = '-'; sync()"
                                                        aria-label="Minus"
                                                        :aria-pressed="operator === '-' ? 'true' : 'false'"
                                                        class="h-7 w-8 rounded-full text-sm font-black leading-none transition-all duration-150"
                                                        :class="operator === '-' ? 'bg-[var(--accent)] text-white shadow-sm ring-1 ring-[var(--accent)]/40' : 'text-gray-500 dark:text-slate-300 hover:text-gray-800 dark:hover:text-slate-100'"
                                                    >-</button>
                                                    <button
                                                        type="button"
                                                        @click="operator = '+'; sync()"
                                                        aria-label="Plus"
                                                        :aria-pressed="operator === '+' ? 'true' : 'false'"
                                                        class="h-7 w-8 rounded-full text-sm font-black leading-none transition-all duration-150"
                                                        :class="operator === '+' ? 'bg-[var(--accent)] text-white shadow-sm ring-1 ring-[var(--accent)]/40' : 'text-gray-500 dark:text-slate-300 hover:text-gray-800 dark:hover:text-slate-100'"
                                                    >+</button>
                                                </div>
                                                <input
                                                    x-ref="deltaInput"
                                                    type="text"
                                                    inputmode="decimal"
                                                    x-model="delta"
                                                    placeholder="0.00"
                                                    :required="editMode === 'delta'"
                                                    class="w-full max-w-[9rem] rounded-md border border-gray-300 bg-transparent px-2 py-1 text-sm font-semibold tabular-nums text-right text-gray-900 focus:border-[var(--accent)] focus:ring-[var(--accent)] dark:text-slate-100 sm:w-24"
                                                    @input="sync()"
                                                    @keydown.enter.stop.prevent="$el.form.requestSubmit()"
                                                    @keydown.escape="cancel()"
                                                >
                                            </div>
                                        </div>

                                        <div x-show="editMode === 'absolute'" x-cloak class="space-y-1">
                                            <div class="flex items-center justify-end gap-2">
                                                <span class="text-[10px] uppercase tracking-[0.2em] text-gray-400">CHF</span>
                                                <input
                                                    x-ref="absoluteInput"
                                                    type="text"
                                                    inputmode="decimal"
                                                    x-model="absolute"
                                                    :required="editMode === 'absolute'"
                                                    class="w-full rounded-md border border-gray-300 bg-transparent px-2 py-1 text-sm font-semibold tabular-nums text-right text-gray-900 focus:border-[var(--accent)] focus:ring-[var(--accent)] dark:text-slate-100 sm:w-36"
                                                    @input="sync()"
                                                    @keydown.enter.stop.prevent="$el.form.requestSubmit()"
                                                    @keydown.escape="cancel()"
                                                >
                                            </div>
                                            <div class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[var(--accent)] sm:text-right">Direkt überschreiben</div>
                                        </div>

                                        <div class="rounded-lg bg-gradient-to-r from-gray-50/90 to-white/90 px-2 py-1 text-[11px] text-gray-500 dark:from-slate-900/70 dark:to-slate-800/60 dark:text-slate-400 sm:text-right">
                                            <span class="uppercase tracking-[0.2em] text-gray-400">Ergebnis</span>
                                            <span
                                                class="mt-1 block tabular-nums font-semibold sm:ml-1 sm:mt-0 sm:inline"
                                                :class="parseFloat(value) < 0 ? 'text-red-700 dark:text-red-200' : 'text-emerald-700 dark:text-emerald-200'"
                                                x-text="editMode === 'delta' ? 'CHF ' + baseDisplay + ' ' + operator + ' ' + deltaDisplay() + ' = ' + resultDisplay() : 'CHF ' + baseDisplay + ' -> ' + resultDisplay()"
                                            ></span>
                                        </div>
                                    </div>
                                </div>
                                <button x-show="editing" x-cloak type="submit" class="touch-target w-full rounded-xl bg-[var(--accent)] px-3 py-2 text-xs font-semibold text-white shadow-sm sm:w-auto">OK</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="relative overflow-visible rounded-xl border accent-box bg-gradient-to-br from-emerald-50 via-white to-emerald-100/70 dark:from-emerald-950/40 dark:via-slate-950 dark:to-emerald-900/30 shadow-sm">
        <div class="pointer-events-none absolute -left-12 -top-8 h-24 w-24 rounded-full bg-emerald-200/50 dark:bg-emerald-500/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -right-10 bottom-0 h-20 w-20 rounded-full bg-amber-200/40 dark:bg-amber-500/10 blur-2xl"></div>
        <div class="relative p-3">
            <div class="sm:hidden space-y-2">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[11px] uppercase tracking-[0.35em] text-gray-500">Monat</div>
                        <div class="flex items-center gap-2">
                            @can('update', $month)
                                <a href="{{ route('months.edit', $month) }}" class="touch-target inline-flex h-8 w-8 items-center justify-center rounded-full border border-[var(--border)] bg-white/70 text-gray-500" aria-label="Monat bearbeiten">
                                    <x-icon-edit class="h-4 w-4" />
                                </a>
                            @endcan
                            <div class="text-xl font-semibold text-gray-900 dark:text-slate-100 truncate">{{ $month->name }}</div>
                        </div>
                    </div>
                    <div class="shrink-0 flex flex-col items-end gap-1 text-[11px] text-gray-600">
                        <div>
                            @if ($month->is_current)
                                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800">Aktuell</span>
                            @else
                                <form method="POST" action="{{ route('months.current', $month) }}" onsubmit="return confirm('Diesen Monat als aktuellen Monat setzen?');">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_current" value="1">
                                    <button type="submit" class="inline-flex items-center rounded-full border border-gray-300 bg-white/80 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600">
                                        Nicht aktuell
                                    </button>
                                </form>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-1">
                            @if ($canRollover ?? false)
                                <form method="POST" action="{{ route('months.rollover', $month) }}" onsubmit="return confirm('Offene Posten nach {{ $nextMonth?->name }} übertragen?');">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800">
                                        Übertragen
                                    </button>
                                </form>
                            @endif
                            @if ($canRevert ?? false)
                                <form method="POST" action="{{ route('months.rollover.revert', $month) }}" onsubmit="return confirm('Übertrag aus {{ $prevMonth?->name }} rückgängig machen?');">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-700">
                                        Übertrag rückgängig
                                    </button>
                                </form>
                            @endif
                            @if ($canArchive ?? false)
                                <form method="POST" action="{{ route('months.archive', $month) }}" onsubmit="return confirm('Monat archivieren?');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="inline-flex items-center rounded-full border border-gray-300 bg-white/80 px-2 py-0.5 text-[10px] font-semibold text-gray-700">
                                        Archivieren
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="text-xs text-gray-600 dark:text-slate-300">
                    {{ $month->date_from->format('d.m.Y') }} – {{ $month->date_to->format('d.m.Y') }}
                    <span class="mx-1 text-gray-400">·</span>
                    Lebensunterhalt/Tag CHF {{ $fmt($month->daily_living_cost) }}
                </div>
            </div>
            <div class="hidden sm:flex flex-wrap items-center justify-between gap-2 text-xs text-emerald-800/80 dark:text-emerald-200/80">
                <div class="flex flex-wrap items-center gap-2">
                    @can('update', $month)
                        <button type="button" x-show="!editing" @click="editing = true; $nextTick(() => $refs.editName?.focus())" class="inline-flex items-center accent-icon hover:opacity-80" title="Monat bearbeiten" aria-label="Monat bearbeiten">
                            <x-icon-edit class="w-4 h-4" />
                        </button>
                    @endcan
                    <span class="text-xs font-semibold text-gray-900 dark:text-slate-100">{{ $month->name }}</span>
                    <span class="text-gray-400">•</span>
                    <span>{{ $month->date_from->format('d.m.Y') }} – {{ $month->date_to->format('d.m.Y') }}</span>
                    <span class="text-gray-400">·</span>
                    <span>Lebensunterhalt/Tag CHF {{ $fmt($month->daily_living_cost) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    @if ($canRollover ?? false)
                        <form method="POST" action="{{ route('months.rollover', $month) }}" onsubmit="return confirm('Offene Posten nach {{ $nextMonth?->name }} übertragen?');">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-amber-800 transition hover:opacity-80">
                                Übertragen
                            </button>
                        </form>
                    @endif
                    @if ($canRevert ?? false)
                        <form method="POST" action="{{ route('months.rollover.revert', $month) }}" onsubmit="return confirm('Übertrag aus {{ $prevMonth?->name }} rückgängig machen?');">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-red-50 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-red-700 transition hover:opacity-80">
                                Übertrag rückgängig
                            </button>
                        </form>
                    @endif
                    @if ($canArchive ?? false)
                        <form method="POST" action="{{ route('months.archive', $month) }}" onsubmit="return confirm('Monat archivieren?');">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-white/80 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-gray-600 transition hover:opacity-80">
                                Archivieren
                            </button>
                        </form>
                    @endif
                    @if ($month->is_current)
                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800">Aktuell</span>
                    @else
                        <form method="POST" action="{{ route('months.current', $month) }}" onsubmit="return confirm('Diesen Monat als aktuellen Monat setzen?');">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="is_current" value="1">
                            <button type="submit" class="inline-flex items-center rounded-full border border-gray-300 bg-white/80 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600">
                                Nicht aktuell
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="mt-2 grid grid-cols-1 lg:grid-cols-[minmax(0,1fr),auto] items-center gap-3">
                <div class="space-y-1 w-full min-w-0">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs uppercase tracking-[0.2em] text-emerald-700/90">
                        <div class="flex items-center gap-2">
                            <span>Monatsergebnis</span>
                            <x-info-tooltip text="Einnahmen minus offene Ausgaben/Fixkosten dieses Monats. Kontostände zählen nur im aktuellen Monat." />
                        </div>
                    </div>
                    <div class="w-full rounded-lg px-3 py-2 text-center text-2xl font-semibold tabular-nums {{ $monthResultBarClass }}">
                        CHF {{ $fmt($metrics['result'] ?? 0) }}
                    </div>
                </div>
                <div class="space-y-1 w-full lg:justify-self-end">
                    <div class="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-emerald-700/90">
                        <span>Kummuliert ab heute</span>
                        <x-info-tooltip text="Zeigt, was ab heute bis Monatsende übrig bleibt (Einnahmen minus Ausgaben/Fixkosten ab heute)." />
                    </div>
                    <div class="grid grid-cols-1 {{ $hideWorkdayMetrics ? '' : 'sm:grid-cols-3' }} gap-2 w-full max-w-none sm:max-w-[50vw]">
                        <div class="rounded-lg border accent-box bg-white/80 dark:bg-slate-900/70 px-2 py-1">
                            <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-gray-500">
                                <span>Resultat</span>
                                <x-info-tooltip text="Kumuliertes Ergebnis ab heute." />
                            </div>
                            <div class="text-sm font-semibold tabular-nums {{ $resultTextClass }}">{{ $fmt($metrics['cumulative_result'] ?? 0) }}</div>
                        </div>
                        @if (! $hideWorkdayMetrics)
                            <div class="rounded-lg border accent-box bg-white/80 dark:bg-slate-900/70 px-2 py-1">
                                <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-gray-500">
                                    <span>Arbeitstage</span>
                                    <x-info-tooltip text="Verbleibende Arbeitstage ab heute (nur für Selbstständige)." />
                                </div>
                                <div class="text-sm font-semibold tabular-nums text-gray-900">{{ $metrics['cumulative_workdays'] ?? 0 }}</div>
                            </div>
                            <div class="rounded-lg border accent-box bg-white/80 dark:bg-slate-900/70 px-2 py-1">
                                <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-gray-500">
                                    <span>Umsatz/AT</span>
                                    <x-info-tooltip text="Erforderlicher Umsatz pro Arbeitstag ab heute." />
                                </div>
                                <div class="text-sm font-semibold tabular-nums {{ $resultTextClass }}">{{ $fmt($metrics['required_revenue_per_workday_from_today'] ?? 0) }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @can('update', $month)
                <form x-show="editing" x-cloak x-ref="editForm" method="POST" action="{{ route('months.update', $month) }}" class="mt-2 hidden sm:flex flex-wrap items-end gap-2 text-xs">
                    @csrf
                    @method('PUT')
                    <input x-ref="editName" type="text" name="name" value="{{ $month->name }}" class="h-8 w-40 rounded border border-gray-300 bg-white/80 px-2 text-xs text-gray-900 focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                    <input type="date" name="date_from" value="{{ $month->date_from->format('Y-m-d') }}" class="h-8 rounded border border-gray-300 bg-white/80 px-2 text-xs text-gray-700 focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                    <input type="date" name="date_to" value="{{ $month->date_to->format('Y-m-d') }}" class="h-8 rounded border border-gray-300 bg-white/80 px-2 text-xs text-gray-700 focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                    <input type="number" step="0.01" name="daily_living_cost" value="{{ number_format((float) $month->daily_living_cost, 2, '.', '') }}" class="h-8 w-28 rounded border border-gray-300 bg-white/80 px-2 text-sm text-right tabular-nums text-gray-700 focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                    <button type="submit" class="h-8 rounded bg-[var(--accent)] px-3 text-xs font-semibold text-white">Speichern</button>
                    <button type="button" class="h-8 rounded border border-gray-300 px-3 text-xs font-semibold text-gray-600" @click="editing = false; $nextTick(() => $refs.editForm?.reset())">Abbrechen</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="sm:hidden space-y-4">
        <section class="space-y-2">
            <div class="sticky top-[var(--mobile-header-offset)] z-[850] bg-[var(--surface-2)] px-[5px] -mx-[5px] pt-2 pb-2 border-b border-[var(--border)]">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Einnahmen</h3>
                        <div class="text-xs text-gray-500">Offen</div>
                    </div>
                    <div class="text-base font-semibold text-gray-900 dark:text-slate-100">CHF {{ $fmt($incomeSum) }}</div>
                </div>
            </div>
            @php
                $forecastIncomeGroups = $customerIncomes->groupBy('account_id');
                $forecastAccountIds = $forecastAccounts->pluck('id')->all();
                $ungroupedForecastIncomes = $customerIncomes->filter(fn ($income) => ! in_array($income->account_id, $forecastAccountIds, true));
                $hasIncomeCards = $customerIncomes->isNotEmpty()
                    || $recurringIncomes->isNotEmpty()
                    || $manualIncomes->isNotEmpty();
            @endphp

            @if (! $hasIncomeCards)
                <div class="rounded-2xl border border-dashed border-[var(--border)] bg-green-50/60 dark:bg-emerald-950/20 p-4 text-sm text-gray-500">Keine offenen Einnahmen.</div>
            @else
                <div class="space-y-3">
                    @foreach ($forecastAccounts as $forecastAccount)
                        @php
                            $accountIncomes = $forecastIncomeGroups->get($forecastAccount->id, collect());
                        @endphp
                        @if ($accountIncomes->isNotEmpty())
                            <div class="sticky top-[var(--mobile-subheader-offset)] z-[840] bg-[var(--surface-2)] px-[5px] -mx-[5px] py-1 border-b border-[var(--border)] text-[11px] uppercase tracking-[0.25em] text-emerald-700/80">
                                Erwartet · {{ $forecastAccount->name }}
                            </div>
                            <div class="space-y-1.5">
                                @foreach ($accountIncomes as $income)
                                    @include('months.partials.mobile-income-card', ['income' => $income])
                                @endforeach
                            </div>
                        @endif
                    @endforeach

                    @if ($ungroupedForecastIncomes->isNotEmpty())
                        <div class="sticky top-[var(--mobile-subheader-offset)] z-[840] bg-[var(--surface-2)] px-[5px] -mx-[5px] py-1 border-b border-[var(--border)] text-[11px] uppercase tracking-[0.25em] text-emerald-700/80">
                            Erwartete Einnahmen
                        </div>
                        <div class="space-y-1.5">
                            @foreach ($ungroupedForecastIncomes as $income)
                                @include('months.partials.mobile-income-card', ['income' => $income])
                            @endforeach
                        </div>
                    @endif

                    @if ($recurringIncomes->isNotEmpty())
                        <div class="sticky top-[var(--mobile-subheader-offset)] z-[840] bg-[var(--surface-2)] px-[5px] -mx-[5px] py-1 border-b border-[var(--border)] text-[11px] uppercase tracking-[0.25em] text-emerald-700/80">
                            Wiederkehrende Einnahmen
                        </div>
                        <div class="space-y-1.5">
                            @foreach ($recurringIncomes as $income)
                                @include('months.partials.mobile-income-card', ['income' => $income])
                            @endforeach
                        </div>
                    @endif

                    @if ($manualIncomes->isNotEmpty())
                        <div class="sticky top-[var(--mobile-subheader-offset)] z-[840] bg-[var(--surface-2)] px-[5px] -mx-[5px] py-1 border-b border-[var(--border)] text-[11px] uppercase tracking-[0.25em] text-emerald-700/80">
                            Weitere Einnahmen
                        </div>
                        <div class="space-y-1.5">
                            @foreach ($manualIncomes as $income)
                                @include('months.partials.mobile-income-card', ['income' => $income])
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <section class="space-y-2">
            <div class="sticky top-[var(--mobile-header-offset)] z-[850] bg-[var(--surface-2)] px-[5px] -mx-[5px] pt-2 pb-2 border-b border-[var(--border)]">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Rechnungen</h3>
                        <div class="text-xs text-gray-500">Offen</div>
                    </div>
                    <div class="text-base font-semibold text-gray-900 dark:text-slate-100">CHF {{ $fmt($expenseSum) }}</div>
                </div>
            </div>
            <div class="space-y-1.5">
                @forelse ($expenses as $expense)
                    @php
                        $carryoverLabel = $expense->originMonth?->name ?? $expense->movedFromMonth?->name;
                    @endphp
                    <div class="rounded-2xl border border-[var(--border)] bg-blue-50/70 dark:bg-blue-950/30 shadow-sm p-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0 flex items-center gap-2">
                                <a href="{{ route('entries.edit', $expense) }}" class="touch-target inline-flex items-center justify-center rounded-full text-gray-400 hover:text-gray-700" aria-label="Bearbeiten">
                                    <x-icon-edit class="h-4 w-4" />
                                </a>
                                @if (! empty($prevMonth) || ! empty($nextMonth))
                                    <div class="flex items-center gap-1 text-gray-400 shrink-0">
                                        @if (! empty($prevMonth))
                                            <form method="POST" action="{{ route('entries.move-prev-month', $expense) }}" onsubmit="return confirm('Eintrag in den Vormonat verschieben?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="touch-target inline-flex items-center justify-center rounded-full hover:text-gray-700" aria-label="Zum Vormonat" title="Zum Vormonat">
                                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M15 18l-6-6 6-6" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                        @if (! empty($nextMonth))
                                            <form method="POST" action="{{ route('entries.move-next-month', $expense) }}" onsubmit="return confirm('Eintrag in den nächsten Monat verschieben?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="touch-target inline-flex items-center justify-center rounded-full hover:text-gray-700" aria-label="Zum nächsten Monat" title="Zum nächsten Monat">
                                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M9 18l6-6-6-6" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-slate-100 truncate">{{ $expense->description }}</div>
                                    @if ($carryoverLabel)
                                        <div class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-amber-800">Aus {{ $carryoverLabel }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <div class="text-sm font-semibold tabular-nums text-gray-900 dark:text-slate-100">CHF {{ $fmt($expense->amount) }}</div>
                                <button type="button" class="touch-target inline-flex items-center justify-center rounded-full text-[var(--accent)]" aria-label="Bezahlt markieren" title="Bezahlt markieren" @click="sheet = 'mark-paid'; payAction = {{ $json(route('entries.pay', $expense)) }}; payLabel = {{ $json($expense->description) }}">
                                    <x-icon-check class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div class="sr-only">Bezahlt markieren</div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-[var(--border)] bg-blue-50/60 dark:bg-blue-950/20 p-4 text-sm text-gray-500">Keine Rechnungen.</div>
                @endforelse
            </div>
        </section>

        <section class="space-y-2">
            <div class="sticky top-[var(--mobile-header-offset)] z-[850] bg-[var(--surface-2)] px-[5px] -mx-[5px] pt-2 pb-2 border-b border-[var(--border)]">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Fixkosten</h3>
                        <div class="text-xs text-gray-500">Offen</div>
                    </div>
                    <div class="text-base font-semibold text-gray-900 dark:text-slate-100">CHF {{ $fmt($fixcostOpenSum) }}</div>
                </div>
            </div>
            <div class="space-y-1.5">
                @if ($includeCurrentLivingCost)
                    <div class="rounded-2xl border border-[var(--border)] bg-amber-50/70 dark:bg-amber-950/30 shadow-sm p-2.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-base font-semibold text-gray-900 dark:text-slate-100">{{ $livingLabel }}</div>
                            </div>
                            <div class="text-lg font-semibold tabular-nums text-gray-900 dark:text-slate-100">CHF {{ $fmt($livingCostBase) }}</div>
                        </div>
                        <div class="sr-only">Fix</div>
                    </div>
                @endif
                @if ($holidayCustomLivingCost > 0)
                    <div class="rounded-2xl border border-[var(--border)] bg-amber-50/70 dark:bg-amber-950/30 shadow-sm p-2.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-base font-semibold text-gray-900 dark:text-slate-100">Ferien-Lebensunterhalt</div>
                            </div>
                            <div class="text-lg font-semibold tabular-nums text-gray-900 dark:text-slate-100">CHF {{ $fmt($holidayCustomLivingCost) }}</div>
                        </div>
                        <div class="sr-only">Fix</div>
                    </div>
                @endif
                @if ($nextMonthLivingCostBase > 0)
                    <div class="rounded-2xl border border-[var(--border)] bg-amber-50/70 dark:bg-amber-950/30 shadow-sm p-2.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-base font-semibold text-gray-900 dark:text-slate-100">Lebensunterhalt nächster Monat</div>
                            </div>
                            <div class="text-lg font-semibold tabular-nums text-gray-900 dark:text-slate-100">CHF {{ $fmt($nextMonthLivingCostBase) }}</div>
                        </div>
                        <div class="sr-only">Fix</div>
                    </div>
                @endif
                @if ($nextMonthHolidayCustomLivingCost > 0)
                    <div class="rounded-2xl border border-[var(--border)] bg-amber-50/70 dark:bg-amber-950/30 shadow-sm p-2.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-base font-semibold text-gray-900 dark:text-slate-100">Ferien-Lebensunterhalt nächster Monat</div>
                            </div>
                            <div class="text-lg font-semibold tabular-nums text-gray-900 dark:text-slate-100">CHF {{ $fmt($nextMonthHolidayCustomLivingCost) }}</div>
                        </div>
                        <div class="sr-only">Fix</div>
                    </div>
                @endif
                @forelse ($fixcosts as $fixcost)
                    @php
                        $isPaid = $fixcost->status === 'paid';
                        $openAmount = $isPaid ? 0 : (float) $fixcost->amount;
                    @endphp
                    @php
                        $carryoverLabel = $fixcost->originMonth?->name ?? $fixcost->movedFromMonth?->name;
                    @endphp
                    <div class="rounded-2xl border border-[var(--border)] bg-amber-50/70 dark:bg-amber-950/30 shadow-sm p-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0 flex items-center gap-2">
                                <a href="{{ route('entries.edit', $fixcost) }}" class="touch-target inline-flex items-center justify-center rounded-full text-gray-400 hover:text-gray-700" aria-label="Bearbeiten">
                                    <x-icon-edit class="h-4 w-4" />
                                </a>
                                @if (! empty($prevMonth) || ! empty($nextMonth))
                                    <div class="flex items-center gap-1 text-gray-400 shrink-0">
                                        @if (! empty($prevMonth))
                                            <form method="POST" action="{{ route('entries.move-prev-month', $fixcost) }}" onsubmit="return confirm('Eintrag in den Vormonat verschieben?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="touch-target inline-flex items-center justify-center rounded-full hover:text-gray-700" aria-label="Zum Vormonat" title="Zum Vormonat">
                                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M15 18l-6-6 6-6" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                        @if (! empty($nextMonth))
                                            <form method="POST" action="{{ route('entries.move-next-month', $fixcost) }}" onsubmit="return confirm('Eintrag in den nächsten Monat verschieben?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="touch-target inline-flex items-center justify-center rounded-full hover:text-gray-700" aria-label="Zum nächsten Monat" title="Zum nächsten Monat">
                                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M9 18l6-6-6-6" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-slate-100 truncate">{{ $fixcost->description }}</div>
                                    @if ($carryoverLabel)
                                        <div class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-amber-800">Aus {{ $carryoverLabel }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <div class="text-sm font-semibold tabular-nums text-gray-900 dark:text-slate-100">CHF {{ $fmt($openAmount) }}</div>
                                @if (! $isPaid)
                                    <button type="button" class="touch-target inline-flex items-center justify-center rounded-full text-[var(--accent)]" aria-label="Bezahlt markieren" title="Bezahlt markieren" @click="sheet = 'mark-paid'; payAction = {{ $json(route('entries.pay', $fixcost)) }}; payLabel = {{ $json($fixcost->description) }}">
                                        <x-icon-check class="h-4 w-4" />
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('entries.toggle-paid', $fixcost) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="touch-target inline-flex items-center justify-center rounded-full text-gray-400 hover:text-gray-700" aria-label="Rückgängig" title="Rückgängig">
                                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M3 12a9 9 0 1 0 3-6.7" />
                                                <path d="M3 4v6h6" />
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        <div class="sr-only">Statusaktion</div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-[var(--border)] bg-amber-50/60 dark:bg-amber-950/20 p-4 text-sm text-gray-500">Keine Fixkosten.</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="hidden sm:grid grid-cols-1 lg:grid-cols-3 gap-3">
        <x-section-table title="Einnahmen" sum="{{ $fmt($incomeSum) }}" bg-class="bg-green-50 dark:bg-emerald-950/30" x-data="{ moveMode: false, addExpected: false }">
            <x-slot name="actions">
                <button type="button" class="inline-flex h-6 w-6 items-center justify-center rounded border border-[var(--accent)] bg-white/80 text-xs font-semibold text-[var(--accent)] transition hover:text-[var(--accent)]" :class="moveMode ? 'border-[var(--accent)] bg-[var(--accent)] text-white' : ''" @click="moveMode = !moveMode" title="Verschieben" aria-label="Verschieben">
                    &harr;
                </button>
                <button type="button" class="inline-flex h-6 w-6 items-center justify-center rounded border border-[var(--accent)] bg-white/80 text-xs font-semibold text-[var(--accent)] transition hover:text-[var(--accent)]" :class="addExpected ? 'border-[var(--accent)] bg-[var(--accent)] text-white' : ''" @click="addExpected = !addExpected" title="Neue Einnahme" aria-label="Neue Einnahme">
                    +
                </button>
            </x-slot>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-600">
                        <th class="pb-2">Name</th>
                        <th class="pb-2 text-right tabular-nums">Betrag</th>
                    </tr>
                </thead>
                <tbody data-sortable data-order-url="{{ route('months.entries.order', $month) }}" data-type="income">
                    @if ($forecastAccounts->isNotEmpty())
                        <tr class="border-t border-green-200 text-[11px] uppercase tracking-wide text-emerald-700/90 dark:text-emerald-200/90">
                            <td class="pt-2 pb-1">Einnahmen erwartet</td>
                            <td class="pt-2 pb-1 text-right"></td>
                        </tr>
                        @php $incomeFormId = 'income-create-'.$month->id; @endphp
                        <x-inline-entry-row :form-id="$incomeFormId" :action="route('months.entries.store', $month)" x-show="addExpected" x-cloak>
                            <x-slot name="hidden">
                                <input type="hidden" name="type" value="income">
                                <input type="hidden" name="direction" value="in">
                                <input type="hidden" name="status" value="open">
                                <input type="hidden" name="income_source" value="expected">
                            </x-slot>
                            <td class="py-2 pr-2">
                                <div class="flex flex-col gap-2 sm:gap-1 sm:{{ $inputStackClass }}">
                                    <input type="text" name="description" form="{{ $incomeFormId }}" class="w-full border border-transparent bg-white/60 px-2 py-2 text-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" placeholder="Neue erwartete Einnahme" required>
                                    <select name="account_id" form="{{ $incomeFormId }}" class="w-full sm:w-auto border border-transparent bg-white/60 px-2 py-2 text-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                                        @foreach ($forecastAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2">
                                    <input type="number" step="0.01" name="amount" form="{{ $incomeFormId }}" class="w-full sm:w-28 border border-transparent bg-white/60 px-2 py-2 text-sm text-right tabular-nums focus:border-[var(--accent)] focus:ring-[var(--accent)]" placeholder="0.00" required>
                                    <button type="submit" form="{{ $incomeFormId }}" class="w-full sm:w-auto px-3 py-2 bg-[var(--accent)] text-white rounded text-xs font-semibold">Hinzufügen</button>
                                </div>
                            </td>
                        </x-inline-entry-row>
                        @foreach ($forecastAccounts as $account)
                            @php
                                $balance = $forecastBalances[$account->id] ?? 0;
                                $balanceClass = $balance < 0 ? 'text-red-700' : 'text-gray-900';
                            @endphp
                            <tr class="border-t border-green-200">
                                <td class="{{ $rowPadClass }} pr-2 font-medium text-gray-900">{{ $account->name }}</td>
                            <td class="{{ $rowPadClass }} text-right tabular-nums font-semibold {{ $balanceClass }}">
                                {{ $fmt($balance) }}
                            </td>
                            </tr>
                        @endforeach
                        <tr class="border-t border-green-200">
                            <td class="{{ $rowPadClass }} pr-2 font-medium text-gray-900 dark:text-slate-100">Wiederkehrende Einnahmen</td>
                            <td class="{{ $rowPadClass }} text-right tabular-nums font-semibold">{{ $fmt($recurringIncomeSum) }}</td>
                        </tr>
                        <tr class="border-t border-green-200">
                            <td class="py-2" colspan="2"></td>
                        </tr>
                    @endif
                    <tr class="border-t border-green-200 text-xs uppercase tracking-wide text-green-900 dark:text-emerald-200">
                        <td class="pt-2 pb-1">Wiederkehrende Einnahmen</td>
                        <td class="pt-2 pb-1 text-right"></td>
                    </tr>
                    @forelse ($recurringIncomes as $income)
                        @php
                            $openAmount = $income->open_amount;
                            $displayAmount = $income->status === 'paid' ? (float) $income->amount : $openAmount;
                            $incomeEditId = 'income-edit-' . $income->id;
                            $incomeAmountInput = number_format((float) $income->amount, 2, '.', '');
                            $incomeAmountDisplay = number_format((float) $income->amount, 2, '.', "'");
                            $currentIsForecastFamily = in_array($income->account?->type, ['forecast', 'clearing'], true);
                            $canSwitchForecastAccount = $currentIsForecastFamily
                                || ($income->recurring_template_id !== null && $forecastAccounts->isNotEmpty());
                            $showCurrentAccountOption = $canSwitchForecastAccount
                                && ! $currentIsForecastFamily
                                && $income->account;
                        @endphp
                        <tr x-data="{
                            editing: false,
                            payOpen: false,
                            description: {{ $json($income->description) }},
                            amount: {{ $json($incomeAmountInput) }},
                            amountDisplay: {{ $json($incomeAmountDisplay) }},
                            accountId: {{ $json((string) ($income->account_id ?? '')) }},
                            originalDescription: {{ $json($income->description) }},
                            originalAmount: {{ $json($incomeAmountInput) }},
                            originalAmountDisplay: {{ $json($incomeAmountDisplay) }},
                            originalAccountId: {{ $json((string) ($income->account_id ?? '')) }},
                            canSwitchForecastAccount: {{ $canSwitchForecastAccount ? 'true' : 'false' }},
                            paymentAmount: {{ $json($openAmount) }},
                            syncAmount() {
                                this.amount = (this.amountDisplay || '').toString().replace(/'/g, '').replace(',', '.');
                            },
                            formatAmount() {
                                const normalized = (this.amountDisplay || '').toString().replace(/'/g, '').replace(',', '.');
                                const num = parseFloat(normalized);
                                if (Number.isNaN(num)) {
                                    return;
                                }
                                const fixed = num.toFixed(2);
                                const parts = fixed.split('.');
                                parts[0] = parts[0].replace(/\\B(?=(\\d{3})+(?!\\d))/g, &quot;'&quot;);
                                this.amountDisplay = parts.join('.');
                                this.amount = fixed;
                            }
                        }" class="border-t border-green-200 cursor-move" draggable="true" data-entry-id="{{ $income->id }}">
                            <td class="{{ $rowPadClass }} pr-2 break-words">
                                <form id="{{ $incomeEditId }}" method="POST" action="{{ route('entries.update', $income) }}" class="hidden">
                                    @csrf
                                    @method('PUT')
                                </form>
                                <input type="hidden" name="entry_date" form="{{ $incomeEditId }}" value="{{ $income->entry_date->format('Y-m-d') }}">
                                <input type="hidden" name="status" form="{{ $incomeEditId }}" value="{{ $income->status }}">
                                <input type="hidden" name="account_id" form="{{ $incomeEditId }}" x-model="accountId" :disabled="canSwitchForecastAccount">
                                <input type="hidden" name="amount" form="{{ $incomeEditId }}" x-model="amount">
                                <div x-show="!editing" class="flex items-start gap-1">
                                    <button type="button" class="inline-flex items-center accent-icon hover:opacity-80" title="Bearbeiten" aria-label="Bearbeiten" @click="editing = true; payOpen = false; $nextTick(() => $refs.description.focus())">
                                        <x-icon-edit class="w-3 h-3" />
                                    </button>
                                    <span>{{ $income->description }}</span>
                                    @include('months.partials.carryover-badge', ['entry' => $income])
                                </div>
                                <input x-show="editing" x-cloak x-ref="description" type="text" name="description" form="{{ $incomeEditId }}" x-model="description" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:border-[var(--accent)] focus:ring-[var(--accent)]" @keydown.enter.stop.prevent="$el.form.submit()">
                                <div x-show="editing && canSwitchForecastAccount" x-cloak class="mt-1">
                                    <select name="account_id" form="{{ $incomeEditId }}" x-model="accountId" :disabled="!editing || !canSwitchForecastAccount" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:border-[var(--accent)] focus:ring-[var(--accent)]">
                                        @if ($showCurrentAccountOption)
                                            <option value="{{ $income->account_id }}">{{ $income->account->name }} (aktuell)</option>
                                        @endif
                                        @foreach ($forecastAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($income->recurringTemplate)
                                    <div x-show="editing" x-cloak class="mt-1 text-[11px] text-gray-500">
                                        Änderung gilt nur für diesen Monat. Willst du die Einnahme generell anpassen,
                                        <a href="{{ route('recurring-templates.edit', $income->recurringTemplate) }}" class="font-semibold underline">klicke hier</a>.
                                    </div>
                                @endif
                                @if ($income->recurringTemplate && ($income->recurringTemplate->remaining_amount !== null || $income->recurringTemplate->ends_on))
                                    <div class="text-xs text-gray-500">
                                        @if ($income->recurringTemplate->remaining_amount !== null)
                                            Restbetrag {{ $income->recurringTemplate->currency }} {{ $fmt($income->recurringTemplate->remaining_amount) }}
                                        @endif
                                        @if ($income->recurringTemplate->remaining_amount !== null && $income->recurringTemplate->ends_on)
                                            <span class="mx-1">·</span>
                                        @endif
                                        @if ($income->recurringTemplate->ends_on)
                                            Ende {{ $income->recurringTemplate->ends_on->format('d.m.Y') }}
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="{{ $rowPadClass }} text-right tabular-nums align-top">
                                <div class="{{ $actionStackClass }}">
                                    <div class="{{ $actionRowClass }}">
                                        <div x-show="!editing" class="flex items-center justify-end gap-2">
                                            <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-prev-month', $income) }}" onsubmit="return confirm('Eintrag in den Vormonat verschieben?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-xs text-[var(--accent)] underline" title="Vorheriger Monat">←</button>
                                            </form>
                                            <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-next-month', $income) }}" onsubmit="return confirm('Eintrag in den nächsten Monat verschieben?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-xs text-[var(--accent)] underline" title="Nächster Monat">→</button>
                                            </form>
                                            <span class="font-semibold">{{ $fmt($displayAmount) }}</span>
                                            @if (abs($openAmount) > 0.00001)
                                                <button type="button" class="inline-flex items-center text-[var(--accent)]" title="Zahlung eingegangen" aria-label="Zahlung eingegangen" @click="payOpen = !payOpen">
                                                    <x-icon-check class="w-3 h-3" />
                                                </button>
                                            @endif
                                        </div>
                                        <div x-show="editing" x-cloak class="flex items-center justify-end gap-2">
                                            <button type="submit" form="{{ $incomeEditId }}" class="{{ $editActionPrimary }}">OK</button>
                                            <button type="button" class="{{ $editActionGhost }}" @click="editing = false; description = originalDescription; amount = originalAmount; amountDisplay = originalAmountDisplay; accountId = originalAccountId;">X</button>
                                            <form method="POST" action="{{ route('entries.destroy', $income) }}" onsubmit="return confirm('Eintrag wirklich löschen?');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="redirect_to_month" value="1">
                                                <button type="submit" class="{{ $editActionDanger }}" title="Löschen" aria-label="Löschen">
                                                    <x-icon-trash class="w-3 h-3" />
                                                </button>
                                            </form>
                                            <input type="text" name="amount_display" form="{{ $incomeEditId }}" x-model="amountDisplay" @input="syncAmount()" @blur="formatAmount()" class="w-24 border border-gray-300 rounded px-2 py-1 text-sm text-right tabular-nums focus:border-[var(--accent)] focus:ring-[var(--accent)]" @keydown.enter.stop.prevent="$el.form.submit()">
                                        </div>
                                    </div>
                                    @if (abs($openAmount) > 0.00001)
                                        <form x-show="payOpen && !editing" x-cloak @click.outside="payOpen = false" method="POST" action="{{ route('months.income-payments.store', $month) }}" class="mt-1 flex flex-wrap items-center justify-end gap-2 text-xs">
                                            @csrf
                                            <input type="hidden" name="entry_id" value="{{ $income->id }}">
                                            <input type="number" step="0.01" name="amount" x-model="paymentAmount" class="w-24 border border-gray-300 rounded px-2 py-1 text-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                                            <select name="target_account_id" class="border border-gray-300 rounded px-2 py-1 text-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                                                @foreach ($istAccounts as $account)
                                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="px-2 py-1 bg-[var(--accent)] text-white rounded text-xs">OK</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="border-t border-green-200">
                            <td class="py-2 text-gray-500" colspan="2">Keine wiederkehrenden Einnahmen.</td>
                        </tr>
                    @endforelse

                    @if ($manualIncomes->isNotEmpty())
                        <tr class="border-t border-green-200 text-xs uppercase tracking-wide text-green-900 dark:text-emerald-200">
                            <td class="pt-2 pb-1">Weitere Einnahmen</td>
                            <td class="pt-2 pb-1 text-right"></td>
                        </tr>
                        @foreach ($manualIncomes as $income)
                            @php
                                $openAmount = $income->open_amount;
                                $displayAmount = $income->status === 'paid' ? (float) $income->amount : $openAmount;
                                $incomeEditId = 'income-edit-' . $income->id;
                                $incomeAmountInput = number_format((float) $income->amount, 2, '.', '');
                                $incomeAmountDisplay = number_format((float) $income->amount, 2, '.', "'");
                            @endphp
                            <tr x-data="{
                                editing: false,
                                payOpen: false,
                                description: {{ $json($income->description) }},
                                amount: {{ $json($incomeAmountInput) }},
                                amountDisplay: {{ $json($incomeAmountDisplay) }},
                                accountId: {{ $json((string) ($income->account_id ?? '')) }},
                                originalDescription: {{ $json($income->description) }},
                                originalAmount: {{ $json($incomeAmountInput) }},
                                originalAmountDisplay: {{ $json($incomeAmountDisplay) }},
                                originalAccountId: {{ $json((string) ($income->account_id ?? '')) }},
                                canSwitchForecastAccount: {{ in_array($income->account?->type, ['forecast', 'clearing'], true) ? 'true' : 'false' }},
                                paymentAmount: {{ $json($openAmount) }},
                                syncAmount() {
                                    this.amount = (this.amountDisplay || '').toString().replace(/'/g, '').replace(',', '.');
                                },
                                formatAmount() {
                                    const normalized = (this.amountDisplay || '').toString().replace(/'/g, '').replace(',', '.');
                                    const num = parseFloat(normalized);
                                    if (Number.isNaN(num)) {
                                        return;
                                    }
                                    const fixed = num.toFixed(2);
                                    const parts = fixed.split('.');
                                    parts[0] = parts[0].replace(/\\B(?=(\\d{3})+(?!\\d))/g, &quot;'&quot;);
                                    this.amountDisplay = parts.join('.');
                                    this.amount = fixed;
                                }
                            }" class="border-t border-green-200 cursor-move" draggable="true" data-entry-id="{{ $income->id }}">
                                <td class="{{ $rowPadClass }} pr-2 break-words">
                                    <form id="{{ $incomeEditId }}" method="POST" action="{{ route('entries.update', $income) }}" class="hidden">
                                        @csrf
                                        @method('PUT')
                                    </form>
                                    <input type="hidden" name="entry_date" form="{{ $incomeEditId }}" value="{{ $income->entry_date->format('Y-m-d') }}">
                                    <input type="hidden" name="status" form="{{ $incomeEditId }}" value="{{ $income->status }}">
                                    <input type="hidden" name="account_id" form="{{ $incomeEditId }}" x-model="accountId" :disabled="canSwitchForecastAccount">
                                    <input type="hidden" name="amount" form="{{ $incomeEditId }}" x-model="amount">
                                    <div x-show="!editing" class="flex items-start gap-1">
                                        <button type="button" class="inline-flex items-center accent-icon hover:opacity-80" title="Bearbeiten" aria-label="Bearbeiten" @click="editing = true; payOpen = false; $nextTick(() => $refs.description.focus())">
                                            <x-icon-edit class="w-3 h-3" />
                                        </button>
                                        <span>{{ $income->description }}</span>
                                        @include('months.partials.carryover-badge', ['entry' => $income])
                                    </div>
                                    <input x-show="editing" x-cloak x-ref="description" type="text" name="description" form="{{ $incomeEditId }}" x-model="description" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:border-[var(--accent)] focus:ring-[var(--accent)]" @keydown.enter.stop.prevent="$el.form.submit()">
                                    <div x-show="editing && canSwitchForecastAccount" x-cloak class="mt-1">
                                        <select name="account_id" form="{{ $incomeEditId }}" x-model="accountId" :disabled="!editing || !canSwitchForecastAccount" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:border-[var(--accent)] focus:ring-[var(--accent)]">
                                            @foreach ($forecastAccounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @if ($income->recurringTemplate)
                                        <div x-show="editing" x-cloak class="mt-1 text-[11px] text-gray-500">
                                            Änderung gilt nur für diesen Monat. Willst du die Einnahme generell anpassen,
                                            <a href="{{ route('recurring-templates.edit', $income->recurringTemplate) }}" class="font-semibold underline">klicke hier</a>.
                                        </div>
                                    @endif
                                    @if ($income->recurringTemplate && ($income->recurringTemplate->remaining_amount !== null || $income->recurringTemplate->ends_on))
                                        <div class="text-xs text-gray-500">
                                            @if ($income->recurringTemplate->remaining_amount !== null)
                                                Restbetrag {{ $income->recurringTemplate->currency }} {{ $fmt($income->recurringTemplate->remaining_amount) }}
                                            @endif
                                            @if ($income->recurringTemplate->remaining_amount !== null && $income->recurringTemplate->ends_on)
                                                <span class="mx-1">·</span>
                                            @endif
                                            @if ($income->recurringTemplate->ends_on)
                                                Ende {{ $income->recurringTemplate->ends_on->format('d.m.Y') }}
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="{{ $rowPadClass }} text-right tabular-nums align-top">
                                    <div class="{{ $actionStackClass }}">
                                        <div class="{{ $actionRowClass }}">
                                            <div x-show="!editing" class="flex items-center justify-end gap-2">
                                                <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-prev-month', $income) }}" onsubmit="return confirm('Eintrag in den Vormonat verschieben?');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="text-xs text-[var(--accent)] underline" title="Vorheriger Monat">←</button>
                                                </form>
                                                <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-next-month', $income) }}" onsubmit="return confirm('Eintrag in den nächsten Monat verschieben?');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="text-xs text-[var(--accent)] underline" title="Nächster Monat">→</button>
                                                </form>
                                                <span class="font-semibold">{{ $fmt($displayAmount) }}</span>
                                                @if (abs($openAmount) > 0.00001)
                                                    <button type="button" class="inline-flex items-center text-[var(--accent)]" title="Zahlung eingegangen" aria-label="Zahlung eingegangen" @click="payOpen = !payOpen">
                                                        <x-icon-check class="w-3 h-3" />
                                                    </button>
                                                @endif
                                            </div>
                                            <div x-show="editing" x-cloak class="flex items-center justify-end gap-2">
                                                <button type="submit" form="{{ $incomeEditId }}" class="{{ $editActionPrimary }}">OK</button>
                                                <button type="button" class="{{ $editActionGhost }}" @click="editing = false; description = originalDescription; amount = originalAmount; amountDisplay = originalAmountDisplay; accountId = originalAccountId;">X</button>
                                                <form method="POST" action="{{ route('entries.destroy', $income) }}" onsubmit="return confirm('Eintrag wirklich löschen?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="redirect_to_month" value="1">
                                                    <button type="submit" class="{{ $editActionDanger }}" title="Löschen" aria-label="Löschen">
                                                        <x-icon-trash class="w-3 h-3" />
                                                    </button>
                                                </form>
                                                <input type="text" name="amount_display" form="{{ $incomeEditId }}" x-model="amountDisplay" @input="syncAmount()" @blur="formatAmount()" class="w-24 border border-gray-300 rounded px-2 py-1 text-sm text-right tabular-nums focus:border-[var(--accent)] focus:ring-[var(--accent)]" @keydown.enter.stop.prevent="$el.form.submit()">
                                            </div>
                                        </div>
                                        @if (abs($openAmount) > 0.00001)
                                            <form x-show="payOpen && !editing" x-cloak @click.outside="payOpen = false" method="POST" action="{{ route('months.income-payments.store', $month) }}" class="mt-1 flex flex-wrap items-center justify-end gap-2 text-xs">
                                                @csrf
                                                <input type="hidden" name="entry_id" value="{{ $income->id }}">
                                                <input type="number" step="0.01" name="amount" x-model="paymentAmount" class="w-24 border border-gray-300 rounded px-2 py-1 text-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                                                <select name="target_account_id" class="border border-gray-300 rounded px-2 py-1 text-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                                                    @foreach ($istAccounts as $account)
                                                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="px-2 py-1 bg-[var(--accent)] text-white rounded text-xs">OK</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @endif

                    @php
                        $expectedIncomeGroups = $customerIncomes
                            ->groupBy(fn ($entry) => $entry->account_id ?? 0)
                            ->sortBy(fn ($entries) => $entries->first()->account?->name ?? '');
                    @endphp
                    @forelse ($expectedIncomeGroups as $accountEntries)
                        @php
                            $accountName = $accountEntries->first()->account?->name ?? 'Konto';
                        @endphp
                        <tr class="border-t border-green-200 text-xs uppercase tracking-wide text-green-800 dark:text-emerald-200">
                            <td class="pt-2 pb-1">{{ $accountName }}</td>
                            <td class="pt-2 pb-1 text-right"></td>
                        </tr>
                        @foreach ($accountEntries as $income)
                                @php
                                    $incomeEditId = 'income-edit-' . $income->id;
                                    $openAmount = $income->open_amount;
                                    $incomeAmountInput = number_format((float) $income->amount, 2, '.', '');
                                    $incomeAmountDisplay = number_format((float) $income->amount, 2, '.', "'");
                                @endphp
                                <tr x-data="{
                                    editing: false,
                                    payOpen: false,
                                    setRecurring: '0',
                                    description: {{ $json($income->description) }},
                                    amount: {{ $json($incomeAmountInput) }},
                                    amountDisplay: {{ $json($incomeAmountDisplay) }},
                                    accountId: {{ $json((string) ($income->account_id ?? '')) }},
                                    originalDescription: {{ $json($income->description) }},
                                    originalAmount: {{ $json($incomeAmountInput) }},
                                    originalAmountDisplay: {{ $json($incomeAmountDisplay) }},
                                    originalAccountId: {{ $json((string) ($income->account_id ?? '')) }},
                                    canSwitchForecastAccount: {{ in_array($income->account?->type, ['forecast', 'clearing'], true) ? 'true' : 'false' }},
                                    paymentAmount: {{ $json($openAmount) }},
                                    syncAmount() {
                                        this.amount = (this.amountDisplay || '').toString().replace(/'/g, '').replace(',', '.');
                                    },
                                    formatAmount() {
                                        const normalized = (this.amountDisplay || '').toString().replace(/'/g, '').replace(',', '.');
                                        const num = parseFloat(normalized);
                                        if (Number.isNaN(num)) {
                                            return;
                                        }
                                        const fixed = num.toFixed(2);
                                        const parts = fixed.split('.');
                                        parts[0] = parts[0].replace(/\\B(?=(\\d{3})+(?!\\d))/g, &quot;'&quot;);
                                        this.amountDisplay = parts.join('.');
                                        this.amount = fixed;
                                    }
                                }" class="border-t border-green-200 cursor-move" draggable="true" data-entry-id="{{ $income->id }}">
                                    <td class="{{ $rowPadClass }} pr-2 break-words">
                                        <form id="{{ $incomeEditId }}" method="POST" action="{{ route('entries.update', $income) }}" class="hidden">
                                            @csrf
                                            @method('PUT')
                                        </form>
                                        <input type="hidden" name="entry_date" form="{{ $incomeEditId }}" value="{{ $income->entry_date->format('Y-m-d') }}">
                                        <input type="hidden" name="status" form="{{ $incomeEditId }}" value="{{ $income->status }}">
                                        <input type="hidden" name="set_recurring" form="{{ $incomeEditId }}" x-model="setRecurring">
                                        <input type="hidden" name="account_id" form="{{ $incomeEditId }}" x-model="accountId" :disabled="canSwitchForecastAccount">
                                        <input type="hidden" name="amount" form="{{ $incomeEditId }}" x-model="amount">
                                        <div x-show="!editing" class="flex items-start gap-1">
                                            <button type="button" class="inline-flex items-center accent-icon hover:opacity-80" title="Bearbeiten" aria-label="Bearbeiten" @click="editing = true; payOpen = false; $nextTick(() => $refs.description.focus())">
                                                <x-icon-edit class="w-3 h-3" />
                                            </button>
                                            <span>{{ $income->description }}</span>
                                            @include('months.partials.carryover-badge', ['entry' => $income])
                                        </div>
                                        <input x-show="editing" x-cloak x-ref="description" type="text" name="description" form="{{ $incomeEditId }}" x-model="description" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:border-[var(--accent)] focus:ring-[var(--accent)]" @keydown.enter.stop.prevent="$el.form.submit()">
                                        <div x-show="editing && canSwitchForecastAccount" x-cloak class="mt-1">
                                            <select name="account_id" form="{{ $incomeEditId }}" x-model="accountId" :disabled="!editing || !canSwitchForecastAccount" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:border-[var(--accent)] focus:ring-[var(--accent)]">
                                                @foreach ($forecastAccounts as $account)
                                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @if ($income->recurringTemplate)
                                            <div x-show="editing" x-cloak class="mt-1 text-[11px] text-gray-500">
                                                Änderung gilt nur für diesen Monat. Willst du die Einnahme generell anpassen,
                                                <a href="{{ route('recurring-templates.edit', $income->recurringTemplate) }}" class="font-semibold underline">klicke hier</a>.
                                            </div>
                                        @endif
                                        @if ($income->recurringTemplate && ($income->recurringTemplate->remaining_amount !== null || $income->recurringTemplate->ends_on))
                                            <div class="text-xs text-gray-500">
                                                @if ($income->recurringTemplate->remaining_amount !== null)
                                                    Restbetrag {{ $income->recurringTemplate->currency }} {{ $fmt($income->recurringTemplate->remaining_amount) }}
                                                @endif
                                                @if ($income->recurringTemplate->remaining_amount !== null && $income->recurringTemplate->ends_on)
                                                    <span class="mx-1">·</span>
                                                @endif
                                                @if ($income->recurringTemplate->ends_on)
                                                    Ende {{ $income->recurringTemplate->ends_on->format('d.m.Y') }}
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="{{ $rowPadClass }} text-right tabular-nums align-top">
                                        <div class="{{ $actionStackClass }}">
                                            <div class="{{ $actionRowClass }}">
                                                <div x-show="!editing" class="flex items-center justify-end gap-2">
                                                    <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-prev-month', $income) }}" onsubmit="return confirm('Eintrag in den Vormonat verschieben?');">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="text-xs text-[var(--accent)] underline" title="Vorheriger Monat">←</button>
                                                    </form>
                                                    <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-next-month', $income) }}" onsubmit="return confirm('Eintrag in den nächsten Monat verschieben?');">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="text-xs text-[var(--accent)] underline" title="Nächster Monat">→</button>
                                                    </form>
                                                    <span class="font-semibold">{{ $fmt($openAmount) }}</span>
                                                    @if (abs($openAmount) > 0.00001)
                                                        <button type="button" class="inline-flex items-center text-[var(--accent)]" title="Zahlung eingegangen" aria-label="Zahlung eingegangen" @click="payOpen = !payOpen">
                                                            <x-icon-check class="w-3 h-3" />
                                                        </button>
                                                    @endif
                                                </div>
                                                <div x-show="editing" x-cloak class="flex items-center justify-end gap-2">
                                                    <button type="submit" form="{{ $incomeEditId }}" class="{{ $editActionPrimary }}" @click="setRecurring = '0'">OK</button>
                                                    @if ($income->recurringTemplate)
                                                        <button type="submit" form="{{ $incomeEditId }}" class="{{ $editActionGhost }}" @click="setRecurring = '1'">Wiederkehrend</button>
                                                    @endif
                                                    <button type="button" class="{{ $editActionGhost }}" @click="editing = false; setRecurring = '0'; description = originalDescription; amount = originalAmount; amountDisplay = originalAmountDisplay; accountId = originalAccountId;">X</button>
                                                    <form method="POST" action="{{ route('entries.destroy', $income) }}" onsubmit="return confirm('Eintrag wirklich löschen?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="redirect_to_month" value="1">
                                                        <button type="submit" class="{{ $editActionDanger }}" title="Löschen" aria-label="Löschen">
                                                            <x-icon-trash class="w-3 h-3" />
                                                        </button>
                                                    </form>
                                                    <input type="text" name="amount_display" form="{{ $incomeEditId }}" x-model="amountDisplay" @input="syncAmount()" @blur="formatAmount()" class="w-24 border border-gray-300 rounded px-2 py-1 text-sm text-right tabular-nums focus:border-[var(--accent)] focus:ring-[var(--accent)]" @keydown.enter.stop.prevent="$el.form.submit()">
                                                </div>
                                            </div>
                                            @if (abs($openAmount) > 0.00001)
                                                <form x-show="payOpen && !editing" x-cloak @click.outside="payOpen = false" method="POST" action="{{ route('months.income-payments.store', $month) }}" class="mt-1 flex flex-wrap items-center justify-end gap-2 text-xs">
                                                    @csrf
                                                    <input type="hidden" name="entry_id" value="{{ $income->id }}">
                                                    <input type="number" step="0.01" name="amount" x-model="paymentAmount" class="w-24 border border-gray-300 rounded px-2 py-1 text-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                                                    <select name="target_account_id" class="border border-gray-300 rounded px-2 py-1 text-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                                                        @foreach ($istAccounts as $account)
                                                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="px-2 py-1 bg-[var(--accent)] text-white rounded text-xs">OK</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                        @endforeach
                    @empty
                        <tr class="border-t border-green-200">
                            <td class="py-2 text-gray-500" colspan="2">Keine offenen Einnahmen.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-section-table>

        <x-section-table title="Rechnungen" sum="{{ $fmt($expenseSum) }}" bg-class="bg-blue-50 dark:bg-blue-950/30" x-data="{ moveMode: false, addExpense: false }">
            <x-slot name="actions">
                <button type="button" class="inline-flex h-6 w-6 items-center justify-center rounded border border-[var(--accent)] bg-white/80 text-xs font-semibold text-[var(--accent)] transition hover:text-[var(--accent)]" :class="moveMode ? 'border-[var(--accent)] bg-[var(--accent)] text-white' : ''" @click="moveMode = !moveMode" title="Verschieben" aria-label="Verschieben">
                    &harr;
                </button>
                <button type="button" class="inline-flex h-6 w-6 items-center justify-center rounded border border-[var(--accent)] bg-white/80 text-xs font-semibold text-[var(--accent)] transition hover:text-[var(--accent)]" :class="addExpense ? 'border-[var(--accent)] bg-[var(--accent)] text-white' : ''" @click="addExpense = !addExpense" title="Neue Rechnung" aria-label="Neue Rechnung">
                    +
                </button>
            </x-slot>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-600">
                        <th class="pb-2">Name</th>
                        <th class="pb-2 text-right tabular-nums">Betrag</th>
                    </tr>
                </thead>
                <tbody data-sortable data-order-url="{{ route('months.entries.order', $month) }}" data-type="expense">
                    @forelse ($expenses as $expense)
                        @php
                            $expenseEditId = 'expense-edit-' . $expense->id;
                            $expenseAmountInput = number_format((float) $expense->amount, 2, '.', '');
                            $expenseAmountDisplay = number_format((float) $expense->amount, 2, '.', "'");
                            $expenseDueDate = $expense->due_date?->format('Y-m-d') ?? $expense->entry_date->format('Y-m-d');
                            $expenseIsPaid = $expense->status === 'paid';
                        @endphp
                        <tr x-data="{
                            highlight: false,
                            editing: false,
                            payOpen: false,
                            description: {{ $json($expense->description) }},
                            amount: {{ $json($expenseAmountInput) }},
                            amountDisplay: {{ $json($expenseAmountDisplay) }},
                            originalDescription: {{ $json($expense->description) }},
                            originalAmount: {{ $json($expenseAmountInput) }},
                            originalAmountDisplay: {{ $json($expenseAmountDisplay) }},
                            syncAmount() {
                                this.amount = (this.amountDisplay || '').toString().replace(/'/g, '').replace(',', '.');
                            },
                            formatAmount() {
                                const normalized = (this.amountDisplay || '').toString().replace(/'/g, '').replace(',', '.');
                                const num = parseFloat(normalized);
                                if (Number.isNaN(num)) {
                                    return;
                                }
                                const fixed = num.toFixed(2);
                                const parts = fixed.split('.');
                                parts[0] = parts[0].replace(/\\B(?=(\\d{3})+(?!\\d))/g, &quot;'&quot;);
                                this.amountDisplay = parts.join('.');
                                this.amount = fixed;
                            }
                            }" @dblclick="highlight = !highlight" :class="highlight ? 'bg-yellow-200' : ''" title="Doppelklick zum Markieren" class="border-t border-blue-200 cursor-move" draggable="true" data-entry-id="{{ $expense->id }}">
                            <td class="{{ $rowPadClass }} pr-2 break-words">
                                <form id="{{ $expenseEditId }}" method="POST" action="{{ route('entries.update', $expense) }}" class="hidden">
                                    @csrf
                                    @method('PUT')
                                </form>
                                <input type="hidden" name="entry_date" form="{{ $expenseEditId }}" value="{{ $expense->entry_date->format('Y-m-d') }}">
                                <input type="hidden" name="due_date" form="{{ $expenseEditId }}" value="{{ $expenseDueDate }}">
                                <input type="hidden" name="status" form="{{ $expenseEditId }}" value="{{ $expense->status }}">
                                <input type="hidden" name="account_id" form="{{ $expenseEditId }}" value="{{ $expense->account_id }}">
                                <input type="hidden" name="amount" form="{{ $expenseEditId }}" x-model="amount">
                                <div x-show="!editing" class="flex items-start gap-1">
                                    <button type="button" class="inline-flex items-center accent-icon hover:opacity-80" title="Bearbeiten" aria-label="Bearbeiten" @click="editing = true; payOpen = false; $nextTick(() => $refs.description.focus())">
                                        <x-icon-edit class="w-3 h-3" />
                                    </button>
                                    <span>{{ $expense->description }}</span>
                                    @include('months.partials.carryover-badge', ['entry' => $expense])
                                </div>
                                <input x-show="editing" x-cloak x-ref="description" type="text" name="description" form="{{ $expenseEditId }}" x-model="description" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:border-[var(--accent)] focus:ring-[var(--accent)]" @keydown.enter.stop.prevent="$el.form.submit()">
                                @if ($expense->recurringTemplate)
                                    <div x-show="editing" x-cloak class="mt-1 text-[11px] text-gray-500">
                                        Änderung gilt nur für diesen Monat. Willst du die Ausgabe generell anpassen,
                                        <a href="{{ route('recurring-templates.edit', $expense->recurringTemplate) }}" class="font-semibold underline">klicke hier</a>.
                                    </div>
                                @endif
                                @if ($expense->recurringTemplate && ($expense->recurringTemplate->remaining_amount !== null || $expense->recurringTemplate->ends_on))
                                    <div class="text-xs text-gray-500">
                                        @if ($expense->recurringTemplate->remaining_amount !== null)
                                            Restbetrag {{ $expense->recurringTemplate->currency }} {{ $fmt($expense->recurringTemplate->remaining_amount) }}
                                        @endif
                                        @if ($expense->recurringTemplate->remaining_amount !== null && $expense->recurringTemplate->ends_on)
                                            <span class="mx-1">·</span>
                                        @endif
                                        @if ($expense->recurringTemplate->ends_on)
                                            Ende {{ $expense->recurringTemplate->ends_on->format('d.m.Y') }}
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="{{ $rowPadClass }} text-right tabular-nums font-semibold">
                                <div class="{{ $actionStackClass }}">
                                    <div class="{{ $actionRowClass }}">
                                        <div x-show="!editing" class="flex items-center justify-end gap-2">
                                            <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-prev-month', $expense) }}" onsubmit="return confirm('Eintrag in den Vormonat verschieben?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-xs text-[var(--accent)] underline" title="Vorheriger Monat">←</button>
                                            </form>
                                            <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-next-month', $expense) }}" onsubmit="return confirm('Eintrag in den nächsten Monat verschieben?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-xs text-[var(--accent)] underline" title="Nächster Monat">→</button>
                                            </form>
                                            @if ($expenseIsPaid)
                                                <span class="text-xs text-gray-500" @if ($isCompact) title="Bezahlt über {{ $expense->account?->name ?? 'Konto' }}" @endif>
                                                    {{ $isCompact ? 'Bezahlt' : 'Bezahlt über ' . ($expense->account?->name ?? 'Konto') }}
                                                </span>
                                            @endif
                                            <span class="font-semibold">{{ $fmt($expense->amount) }}</span>
                                            @if (! $expenseIsPaid)
                                                <button type="button" class="inline-flex items-center text-[var(--accent)]" title="Bezahlt" aria-label="Bezahlt" @click="payOpen = !payOpen">
                                                    <x-icon-check class="w-3 h-3" />
                                                </button>
                                            @endif
                                        </div>
                                        <div x-show="editing" x-cloak class="flex items-center justify-end gap-2">
                                            <button type="submit" form="{{ $expenseEditId }}" class="{{ $editActionPrimary }}">OK</button>
                                            <button type="button" class="{{ $editActionGhost }}" @click="editing = false; description = originalDescription; amount = originalAmount; amountDisplay = originalAmountDisplay;">X</button>
                                            <form method="POST" action="{{ route('entries.destroy', $expense) }}" onsubmit="return confirm('Eintrag wirklich löschen?');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="redirect_to_month" value="1">
                                                <button type="submit" class="{{ $editActionDanger }}" title="Löschen" aria-label="Löschen">
                                                    <x-icon-trash class="w-3 h-3" />
                                                </button>
                                            </form>
                                            <input type="text" name="amount_display" form="{{ $expenseEditId }}" x-model="amountDisplay" @input="syncAmount()" @blur="formatAmount()" class="w-24 border border-gray-300 rounded px-2 py-1 text-sm text-right tabular-nums focus:border-[var(--accent)] focus:ring-[var(--accent)]" @keydown.enter.stop.prevent="$el.form.submit()">
                                        </div>
                                    </div>
                                    @if (! $expenseIsPaid)
                                        <form x-show="payOpen && !editing" x-cloak @click.outside="payOpen = false" method="POST" action="{{ route('entries.pay', $expense) }}" class="mt-1 flex flex-wrap items-center justify-end gap-2 text-xs">
                                            @csrf
                                            <select name="account_id" class="border border-gray-300 rounded px-2 py-1 text-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                                                @foreach ($payAccounts as $account)
                                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="px-2 py-1 bg-[var(--accent)] text-white rounded text-xs">OK</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="border-t border-blue-200">
                            <td class="py-2 text-gray-500" colspan="2">Keine Rechnungen.</td>
                        </tr>
                    @endforelse

                    @if ($defaultIstAccount)
                        @php $expenseFormId = 'expense-create-'.$month->id; @endphp
                        <x-inline-entry-row :form-id="$expenseFormId" :action="route('months.entries.store', $month)" x-show="addExpense" x-cloak>
                            <x-slot name="hidden">
                                <input type="hidden" name="type" value="expense">
                                <input type="hidden" name="direction" value="out">
                                <input type="hidden" name="status" value="open">
                                <input type="hidden" name="account_id" value="{{ $defaultIstAccount->id }}">
                                <input type="hidden" name="due_date" value="{{ $month->date_to->toDateString() }}">
                            </x-slot>
                            <td class="py-2 pr-2">
                                <input type="text" name="description" form="{{ $expenseFormId }}" class="w-full border border-transparent bg-white/60 px-2 py-1 text-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" placeholder="Neue Rechnung" required>
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                <div class="flex items-center justify-end gap-2">
                                    <input type="number" step="0.01" name="amount" form="{{ $expenseFormId }}" class="w-28 border border-transparent bg-white/60 px-2 py-1 text-sm text-right tabular-nums focus:border-[var(--accent)] focus:ring-[var(--accent)]" placeholder="0.00" required>
                                    <button type="submit" form="{{ $expenseFormId }}" class="px-2 py-1 bg-[var(--accent)] text-white rounded text-xs">+</button>
                                </div>
                            </td>
                        </x-inline-entry-row>
                    @endif
                </tbody>
            </table>
        </x-section-table>

        <x-section-table title="Fixkosten" sum="{{ $fmt($fixcostOpenSum) }}" bg-class="bg-amber-50 dark:bg-amber-950/30" x-data="{ moveMode: false }">
            <x-slot name="actions">
                <button type="button" class="inline-flex h-6 w-6 items-center justify-center rounded border border-[var(--accent)] bg-white/80 text-xs font-semibold text-[var(--accent)] transition hover:text-[var(--accent)]" :class="moveMode ? 'border-[var(--accent)] bg-[var(--accent)] text-white' : ''" @click="moveMode = !moveMode" title="Verschieben" aria-label="Verschieben">
                    &harr;
                </button>
            </x-slot>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-600">
                        <th class="pb-2">Name</th>
                        <th class="pb-2 text-right tabular-nums">Betrag</th>
                    </tr>
                </thead>
                <tbody data-sortable data-order-url="{{ route('months.entries.order', $month) }}" data-type="fixcost">
                    @if ($includeCurrentLivingCost)
                        <tr class="border-t border-amber-200">
                            <td class="{{ $rowPadClass }} pr-2">
                                <div class="flex flex-col">
                                    <span>{{ $livingLabel }}</span>
                                    @if ($holidayDeductedDays > 0)
                                        <span class="text-[10px] text-gray-500">Ferientage abgezogen: {{ $holidayDeductedDays }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="{{ $rowPadClass }} text-right tabular-nums font-semibold">{{ $fmt($livingCostBase) }}</td>
                        </tr>
                        @if ($holidayCustomLivingCost > 0)
                            <tr class="border-t border-amber-200">
                                <td class="{{ $rowPadClass }} pr-2 text-gray-700">Ferien-Lebensunterhalt</td>
                                <td class="{{ $rowPadClass }} text-right tabular-nums font-semibold">{{ $fmt($holidayCustomLivingCost) }}</td>
                            </tr>
                        @endif
                    @endif
                    @if ($nextMonthLivingCostBase > 0)
                        <tr class="border-t border-amber-200">
                            <td class="{{ $rowPadClass }} pr-2 text-gray-700">
                                <div class="flex flex-col">
                                    <span>Lebensunterhalt nächster Monat</span>
                                    @if (! empty($nextMonth?->name))
                                        <span class="text-[10px] text-gray-500">
                                            {{ $nextMonth->name }}@if ($nextMonthLivingCostFromToday) · ab heute @endif
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="{{ $rowPadClass }} text-right tabular-nums font-semibold">{{ $fmt($nextMonthLivingCostBase) }}</td>
                        </tr>
                    @endif
                    @if ($nextMonthHolidayCustomLivingCost > 0)
                        <tr class="border-t border-amber-200">
                            <td class="{{ $rowPadClass }} pr-2 text-gray-700">
                                <div class="flex flex-col">
                                    <span>Ferien-Lebensunterhalt nächster Monat</span>
                                    @if (! empty($nextMonth?->name))
                                        <span class="text-[10px] text-gray-500">
                                            {{ $nextMonth->name }}@if ($nextMonthLivingCostFromToday) · ab heute @endif
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="{{ $rowPadClass }} text-right tabular-nums font-semibold">{{ $fmt($nextMonthHolidayCustomLivingCost) }}</td>
                        </tr>
                    @endif

                    <tr class="border-t border-amber-200 text-xs uppercase tracking-wide text-amber-900 dark:text-amber-200">
                        <td class="pt-2 pb-1">Wiederkehrende Kosten</td>
                        <td class="pt-2 pb-1 text-right"></td>
                    </tr>
                    @forelse ($fixcosts as $fixcost)
                        @php
                            $isPaid = $fixcost->status === 'paid';
                            $openAmount = $isPaid ? 0 : (float) $fixcost->amount;
                            $fixcostEditId = 'fixcost-edit-' . $fixcost->id;
                            $fixcostAmountInput = number_format((float) $fixcost->amount, 2, '.', '');
                            $fixcostAmountDisplay = number_format((float) $fixcost->amount, 2, '.', "'");
                        @endphp
                        <tr x-data="{
                            editing: false,
                            payOpen: false,
                            description: {{ $json($fixcost->description) }},
                            amount: {{ $json($fixcostAmountInput) }},
                            amountDisplay: {{ $json($fixcostAmountDisplay) }},
                            originalDescription: {{ $json($fixcost->description) }},
                            originalAmount: {{ $json($fixcostAmountInput) }},
                            originalAmountDisplay: {{ $json($fixcostAmountDisplay) }},
                            syncAmount() {
                                this.amount = (this.amountDisplay || '').toString().replace(/'/g, '').replace(',', '.');
                            },
                            formatAmount() {
                                const normalized = (this.amountDisplay || '').toString().replace(/'/g, '').replace(',', '.');
                                const num = parseFloat(normalized);
                                if (Number.isNaN(num)) {
                                    return;
                                }
                                const fixed = num.toFixed(2);
                                const parts = fixed.split('.');
                                parts[0] = parts[0].replace(/\\B(?=(\\d{3})+(?!\\d))/g, &quot;'&quot;);
                                this.amountDisplay = parts.join('.');
                                this.amount = fixed;
                            }
                        }" class="border-t border-amber-200 cursor-move" draggable="true" data-entry-id="{{ $fixcost->id }}">
                            <td class="{{ $rowPadClass }} pr-2 break-words">
                                <form id="{{ $fixcostEditId }}" method="POST" action="{{ route('entries.update', $fixcost) }}" class="hidden">
                                    @csrf
                                    @method('PUT')
                                </form>
                                <input type="hidden" name="entry_date" form="{{ $fixcostEditId }}" value="{{ $fixcost->entry_date->format('Y-m-d') }}">
                                <input type="hidden" name="status" form="{{ $fixcostEditId }}" value="{{ $fixcost->status }}">
                                <input type="hidden" name="account_id" form="{{ $fixcostEditId }}" value="{{ $fixcost->account_id }}">
                                <input type="hidden" name="amount" form="{{ $fixcostEditId }}" x-model="amount">
                                <div x-show="!editing" class="flex items-start gap-1">
                                    <button type="button" class="inline-flex items-center accent-icon hover:opacity-80" title="Bearbeiten" aria-label="Bearbeiten" @click="editing = true; payOpen = false; $nextTick(() => $refs.description.focus())">
                                        <x-icon-edit class="w-3 h-3" />
                                    </button>
                                    <span>{{ $fixcost->description }}</span>
                                    @include('months.partials.carryover-badge', ['entry' => $fixcost])
                                </div>
                                <input x-show="editing" x-cloak x-ref="description" type="text" name="description" form="{{ $fixcostEditId }}" x-model="description" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:border-[var(--accent)] focus:ring-[var(--accent)]" @keydown.enter.stop.prevent="$el.form.submit()">
                                @if ($fixcost->recurringTemplate)
                                    <div x-show="editing" x-cloak class="mt-1 text-[11px] text-gray-500">
                                        Änderung gilt nur für diesen Monat. Willst du die Ausgabe generell anpassen,
                                        <a href="{{ route('recurring-templates.edit', $fixcost->recurringTemplate) }}" class="font-semibold underline">klicke hier</a>.
                                    </div>
                                @endif
                                @if ($fixcost->recurringTemplate && ($fixcost->recurringTemplate->remaining_amount !== null || $fixcost->recurringTemplate->ends_on))
                                    <div class="text-xs text-gray-500">
                                        @if ($fixcost->recurringTemplate->remaining_amount !== null)
                                            Restbetrag {{ $fixcost->recurringTemplate->currency }} {{ $fmt($fixcost->recurringTemplate->remaining_amount) }}
                                        @endif
                                        @if ($fixcost->recurringTemplate->remaining_amount !== null && $fixcost->recurringTemplate->ends_on)
                                            <span class="mx-1">·</span>
                                        @endif
                                        @if ($fixcost->recurringTemplate->ends_on)
                                            Ende {{ $fixcost->recurringTemplate->ends_on->format('d.m.Y') }}
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="{{ $rowPadClass }} text-right tabular-nums align-top">
                                <div class="{{ $actionStackClass }}">
                                    <div class="{{ $actionRowClass }}">
                                        <div x-show="!editing" class="flex items-center justify-end gap-2">
                                            <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-prev-month', $fixcost) }}" onsubmit="return confirm('Eintrag in den Vormonat verschieben?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-xs text-[var(--accent)] underline" title="Vorheriger Monat">←</button>
                                            </form>
                                            <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-next-month', $fixcost) }}" onsubmit="return confirm('Eintrag in den nächsten Monat verschieben?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-xs text-[var(--accent)] underline" title="Nächster Monat">→</button>
                                            </form>
                                            @if ($isPaid)
                                                <span class="text-xs text-gray-500" @if ($isCompact) title="Bezahlt über {{ $fixcost->account?->name ?? 'Konto' }}" @endif>
                                                    {{ $isCompact ? 'Bezahlt' : 'Bezahlt über ' . ($fixcost->account?->name ?? 'Konto') }}
                                                </span>
                                                <form method="POST" action="{{ route('entries.toggle-paid', $fixcost) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="text-xs text-[var(--accent)] underline" title="Rückgängig">{{ $isCompact ? '↺' : 'Rückgängig' }}</button>
                                                </form>
                                            @endif
                                            <span class="font-semibold">{{ $fmt($openAmount) }}</span>
                                            @if (! $isPaid)
                                                <button type="button" class="inline-flex items-center text-[var(--accent)]" title="Bezahlt" aria-label="Bezahlt" @click="payOpen = !payOpen">
                                                    <x-icon-check class="w-3 h-3" />
                                                </button>
                                            @endif
                                        </div>
                                        <div x-show="editing" x-cloak class="flex items-center justify-end gap-2">
                                            <button type="submit" form="{{ $fixcostEditId }}" class="{{ $editActionPrimary }}">OK</button>
                                            <button type="button" class="{{ $editActionGhost }}" @click="editing = false; description = originalDescription; amount = originalAmount; amountDisplay = originalAmountDisplay;">X</button>
                                            <form method="POST" action="{{ route('entries.destroy', $fixcost) }}" onsubmit="return confirm('Eintrag wirklich löschen?');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="redirect_to_month" value="1">
                                                <button type="submit" class="{{ $editActionDanger }}" title="Löschen" aria-label="Löschen">
                                                    <x-icon-trash class="w-3 h-3" />
                                                </button>
                                            </form>
                                            <input type="text" name="amount_display" form="{{ $fixcostEditId }}" x-model="amountDisplay" @input="syncAmount()" @blur="formatAmount()" class="w-24 border border-gray-300 rounded px-2 py-1 text-sm text-right tabular-nums focus:border-[var(--accent)] focus:ring-[var(--accent)]" @keydown.enter.stop.prevent="$el.form.submit()">
                                        </div>
                                    </div>
                                    @if (! $isPaid)
                                        <form x-show="payOpen && !editing" x-cloak @click.outside="payOpen = false" method="POST" action="{{ route('entries.pay', $fixcost) }}" class="mt-1 flex flex-wrap items-center justify-end gap-2 text-xs">
                                            @csrf
                                            <select name="account_id" class="border border-gray-300 rounded px-2 py-1 text-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                                                @foreach ($payAccounts as $account)
                                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="px-2 py-1 bg-[var(--accent)] text-white rounded text-xs">OK</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="border-t border-amber-200">
                            <td class="py-2 text-gray-500" colspan="2">Keine wiederkehrenden Kosten.</td>
                        </tr>
                    @endforelse

                </tbody>
            </table>
        </x-section-table>
    </div>

    <x-bottom-sheet show="sheet === 'quick'" close="sheet = null" title="Neu erfassen">
        <div class="grid grid-cols-1 gap-3">
            <button type="button" class="touch-target w-full rounded-2xl bg-[var(--accent)] text-base font-semibold text-white" @click="sheet = 'expense'">Rechnung</button>
            <button type="button" class="touch-target w-full rounded-2xl border border-[var(--border)] bg-white/80 text-base font-semibold text-gray-700 dark:text-slate-100" @click="sheet = 'payment-hub'">Zahlung</button>
            <button type="button" class="touch-target w-full rounded-2xl border border-[var(--border)] bg-white/80 text-base font-semibold text-gray-700 dark:text-slate-100" @click="sheet = 'income'">Erwartete Einnahme</button>
        </div>
    </x-bottom-sheet>

    <x-bottom-sheet show="sheet === 'payment-hub'" close="sheet = null" title="Zahlung">
        <div class="grid grid-cols-1 gap-3">
            <button type="button" class="touch-target w-full rounded-2xl bg-[var(--accent)] text-base font-semibold text-white" @click="sheet = 'payment'">Einnahme verbuchen</button>
            <button type="button" class="touch-target w-full rounded-2xl border border-[var(--border)] bg-white/80 text-base font-semibold text-gray-700 dark:text-slate-100" @click="sheet = 'payment-out'">Rechnung bezahlt</button>
        </div>
    </x-bottom-sheet>

    <x-bottom-sheet show="sheet === 'income'" close="sheet = null" title="Neue Einnahme">
        @php
            $incomeAccounts = $forecastAccounts->isNotEmpty() ? $forecastAccounts : $accounts;
            $incomeSourceDefault = $forecastAccounts->isNotEmpty() ? 'expected' : 'manual';
        @endphp
        <form method="POST" action="{{ route('months.entries.store', $month) }}" class="space-y-4" x-data="{ source: '{{ $incomeSourceDefault }}' }">
            @csrf
            <input type="hidden" name="type" value="income">
            <input type="hidden" name="direction" value="in">
            <input type="hidden" name="status" value="open">
            <div class="space-y-2">
                <label class="text-[11px] uppercase tracking-[0.3em] text-gray-500">Art</label>
                <select name="income_source" x-model="source" class="w-full rounded-xl border border-gray-300 bg-white/80 px-3 py-3 text-base focus:border-[var(--accent)] focus:ring-[var(--accent)]">
                    <option value="expected">Erwartet (Forecast)</option>
                    <option value="manual">Manuell</option>
                </select>
            </div>
            <div class="space-y-2">
                <label class="text-[11px] uppercase tracking-[0.3em] text-gray-500">Beschreibung</label>
                <input data-autofocus type="text" name="description" class="w-full rounded-xl border border-gray-300 bg-white/80 px-3 py-3 text-base focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
            </div>
            <div class="space-y-2">
                <label class="text-[11px] uppercase tracking-[0.3em] text-gray-500">Betrag</label>
                <input type="text" name="amount" class="w-full rounded-xl border border-gray-300 bg-white/80 px-3 py-3 text-base text-right tabular-nums focus:border-[var(--accent)] focus:ring-[var(--accent)]" placeholder="0.00" required>
            </div>
            <div class="space-y-2" x-show="source === 'expected'" x-cloak>
                <label class="text-[11px] uppercase tracking-[0.3em] text-gray-500">Konto</label>
                <select name="account_id" class="w-full rounded-xl border border-gray-300 bg-white/80 px-3 py-3 text-base focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                    @foreach ($incomeAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($defaultForecastAccount)
                <input type="hidden" name="account_id" value="{{ $defaultForecastAccount->id }}" x-bind:disabled="source === 'expected'">
            @endif
            <div class="sticky bottom-0 bg-[var(--surface)] pt-2">
                <button type="submit" class="touch-target w-full rounded-2xl bg-[var(--accent)] text-base font-semibold text-white">Speichern</button>
            </div>
        </form>
    </x-bottom-sheet>

    <x-bottom-sheet show="sheet === 'expense'" close="sheet = null" title="Neue Rechnung">
        <form method="POST" action="{{ route('months.entries.store', $month) }}" class="space-y-4">
            @csrf
            <input type="hidden" name="type" value="expense">
            <input type="hidden" name="direction" value="out">
            <input type="hidden" name="status" value="open">
            <input type="hidden" name="entry_date" value="{{ now()->format('Y-m-d') }}">
            <div class="space-y-2">
                <label class="text-[11px] uppercase tracking-[0.3em] text-gray-500">Beschreibung</label>
                <input data-autofocus type="text" name="description" class="w-full rounded-xl border border-gray-300 bg-white/80 px-3 py-3 text-base focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
            </div>
            <div class="space-y-2">
                <label class="text-[11px] uppercase tracking-[0.3em] text-gray-500">Betrag</label>
                <input type="number" step="0.01" inputmode="decimal" name="amount" class="w-full rounded-xl border border-gray-300 bg-white/80 px-3 py-3 text-base text-right tabular-nums focus:border-[var(--accent)] focus:ring-[var(--accent)]" placeholder="0.00" required>
            </div>
            <div class="space-y-2">
                <label class="text-[11px] uppercase tracking-[0.3em] text-gray-500">Fällig</label>
                <input type="date" name="due_date" value="{{ $month->date_to->format('Y-m-d') }}" class="w-full rounded-xl border border-gray-300 bg-white/80 px-3 py-3 text-base focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
            </div>
            <div class="space-y-2">
                <label class="text-[11px] uppercase tracking-[0.3em] text-gray-500">Konto</label>
                <select name="account_id" class="w-full rounded-xl border border-gray-300 bg-white/80 px-3 py-3 text-base focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                    @foreach ($payAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sticky bottom-0 bg-[var(--surface)] pt-2">
                <button type="submit" class="touch-target w-full rounded-2xl bg-[var(--accent)] text-base font-semibold text-white">Speichern</button>
            </div>
        </form>
    </x-bottom-sheet>

    <x-bottom-sheet show="sheet === 'payment'" close="sheet = null" title="Zahlung eingegangen">
        @if ($incomes->isEmpty())
            <div class="rounded-2xl border border-dashed border-[var(--border)] bg-white/70 p-4 text-sm text-gray-500">Keine offenen Einnahmen vorhanden.</div>
        @else
            <form method="POST" action="{{ route('months.income-payments.store', $month) }}" class="space-y-4" x-init="if (!paymentEntryId) { paymentEntryId = {{ $firstIncomeId ?? 'null' }}; paymentAmount = paymentEntryId ? incomePaymentMap[paymentEntryId] : null; }">
                @csrf
                <div class="space-y-2">
                    <label class="text-[11px] uppercase tracking-[0.3em] text-gray-500">Einnahme</label>
                    <select name="entry_id" x-model="paymentEntryId" @change="paymentAmount = incomePaymentMap[paymentEntryId] ?? paymentAmount" class="w-full rounded-xl border border-gray-300 bg-white/80 px-3 py-3 text-base focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                        @foreach ($incomes as $income)
                            <option value="{{ $income->id }}">{{ $income->description }} (CHF {{ $fmt($income->open_amount) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="text-[11px] uppercase tracking-[0.3em] text-gray-500">Betrag</label>
                    <input type="number" step="0.01" inputmode="decimal" name="amount" x-model="paymentAmount" class="w-full rounded-xl border border-gray-300 bg-white/80 px-3 py-3 text-base text-right tabular-nums focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                </div>
                <div class="space-y-2">
                    <label class="text-[11px] uppercase tracking-[0.3em] text-gray-500">Zielkonto</label>
                    <select name="target_account_id" class="w-full rounded-xl border border-gray-300 bg-white/80 px-3 py-3 text-base focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                        @foreach ($istAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sticky bottom-0 bg-[var(--surface)] pt-2">
                    <button type="submit" class="touch-target w-full rounded-2xl bg-[var(--accent)] text-base font-semibold text-white">Speichern</button>
                </div>
            </form>
        @endif
    </x-bottom-sheet>

    <x-bottom-sheet show="sheet === 'payment-out'" close="sheet = null" title="Rechnung bezahlt">
        @if ($payableEntries->isEmpty())
            <div class="rounded-2xl border border-dashed border-[var(--border)] bg-white/70 p-4 text-sm text-gray-500">Keine offenen Rechnungen oder Fixkosten.</div>
        @else
            <form method="POST" x-bind:action="payableActionMap[payableEntryId] || '#'" class="space-y-4" x-init="if (!payableEntryId) { payableEntryId = {{ $json($firstPayableId) }}; }">
                @csrf
                <div class="space-y-2">
                    <label class="text-[11px] uppercase tracking-[0.3em] text-gray-500">Posten</label>
                    <select x-model="payableEntryId" class="w-full rounded-xl border border-gray-300 bg-white/80 px-3 py-3 text-base focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                        @foreach ($payableEntries as $entry)
                            @php
                                $typeLabel = $entry->type === 'expense' ? 'Rechnung' : 'Fixkosten';
                            @endphp
                            <option value="{{ $entry->id }}">{{ $typeLabel }}: {{ $entry->description }} (CHF {{ $fmt($entry->amount) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="text-[11px] uppercase tracking-[0.3em] text-gray-500">Konto</label>
                    <select name="account_id" class="w-full rounded-xl border border-gray-300 bg-white/80 px-3 py-3 text-base focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                        @foreach ($payAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sticky bottom-0 bg-[var(--surface)] pt-2">
                    <button type="submit" class="touch-target w-full rounded-2xl bg-[var(--accent)] text-base font-semibold text-white" x-bind:disabled="!payableEntryId">Speichern</button>
                </div>
            </form>
        @endif
    </x-bottom-sheet>

    <x-bottom-sheet show="sheet === 'mark-paid'" close="sheet = null; payAction = null; payLabel = ''" title="Bezahlt markieren">
        <form method="POST" x-bind:action="payAction || '#'" class="space-y-4">
            @csrf
            <div class="rounded-2xl border border-[var(--border)] bg-white/70 p-3 text-sm text-gray-600">
                Zahlung für <span class="font-semibold text-gray-900" x-text="payLabel"></span>
            </div>
            <div class="space-y-2">
                <label class="text-[11px] uppercase tracking-[0.3em] text-gray-500">Konto</label>
                <select name="account_id" class="w-full rounded-xl border border-gray-300 bg-white/80 px-3 py-3 text-base focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                    @foreach ($payAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sticky bottom-0 bg-[var(--surface)] pt-2">
                <button type="submit" class="touch-target w-full rounded-2xl bg-[var(--accent)] text-base font-semibold text-white" x-bind:disabled="!payAction">Speichern</button>
            </div>
        </form>
    </x-bottom-sheet>

    <div class="sm:hidden h-28"></div>

    <div class="mt-6">
        <button type="button" class="touch-target w-full rounded-2xl border border-[var(--border)] bg-white/80 text-sm font-semibold text-gray-700 dark:text-slate-100 flex items-center justify-between px-4 py-3" @click="entriesOpen = !entriesOpen; if (entriesOpen) { $nextTick(() => $refs.entriesSection?.scrollIntoView({ behavior: 'smooth', block: 'start' })) }">
            <span class="flex items-center gap-2">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M8 6h13" />
                    <path d="M8 12h13" />
                    <path d="M8 18h13" />
                    <circle cx="4" cy="6" r="1" />
                    <circle cx="4" cy="12" r="1" />
                    <circle cx="4" cy="18" r="1" />
                </svg>
                Einträge (Log)
            </span>
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path x-show="!entriesOpen" x-cloak d="M6 9l6 6 6-6" />
                <path x-show="entriesOpen" x-cloak d="M6 15l6-6 6 6" />
            </svg>
        </button>
    </div>

    <div x-show="entriesOpen" x-cloak x-ref="entriesSection" id="entries-{{ $month->id }}" class="space-y-4">
        @include('months.entries.panel', [
            'month' => $month,
            'entries' => $entriesList ?? $entries,
            'accounts' => $accounts,
            'filters' => $entryFilters ?? [],
            'embedded' => true,
        ])
    </div>
</div>
