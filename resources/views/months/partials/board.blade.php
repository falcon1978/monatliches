@php
    $fmt = fn ($value) => new \Illuminate\Support\HtmlString(
        number_format((float) $value, 2, '.', "'")
    );
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
    $livingLabel = $today->between($month->date_from, $month->date_to, true)
        ? 'Lebensunterhalt ab Heute'
        : 'Lebensunterhalt für diesen Monat';

    $forecastAccounts = $accounts->where('type', 'forecast');
    $balanceAccounts = $accounts->whereIn('type', ['ist', 'clearing']);
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
    $recurringIncomes = $incomes->whereNotNull('recurring_template_id')->values();
    $manualIncomes = $incomes
        ->whereNull('recurring_template_id')
        ->filter(function ($entry) {
            $source = $entry->income_source
                ?? ($entry->account?->type === 'forecast' ? 'expected' : 'manual');

            return $source === 'manual';
        })
        ->values();
    $customerIncomes = $incomes
        ->whereNull('recurring_template_id')
        ->filter(function ($entry) {
            $source = $entry->income_source
                ?? ($entry->account?->type === 'forecast' ? 'expected' : 'manual');

            return $source === 'expected' && in_array($entry->status, ['open', 'partial'], true);
        })
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
@endphp

<div class="space-y-4" x-data="{ entriesOpen: {{ ($entriesOpen ?? false) ? 'true' : 'false' }}, editing: false }" x-init="if (entriesOpen) { $nextTick(() => $refs.entriesSection?.scrollIntoView({ behavior: 'smooth', block: 'start' })) }">

    @if ($pendingTemplates > 0)
        <div class="border border-amber-200 bg-amber-50 text-amber-900 p-3 text-sm flex flex-wrap items-center justify-between gap-3 accent-box">
            <div>Für diesen Monat sind {{ $pendingTemplates }} neue wiederkehrende Posten verfügbar.</div>
            <form method="POST" action="{{ route('months.import-templates', $month) }}">
                @csrf
                <button type="submit" class="px-3 py-1.5 bg-[var(--accent)] text-white rounded text-xs">Übernehmen</button>
            </form>
        </div>
    @endif

    @if (($prevMonthOpenCount ?? 0) > 0 && $month->is_current)
        <div class="border border-amber-200 bg-amber-50 text-amber-900 p-3 text-sm accent-box">
            Im Vormonat {{ $prevMonth?->name }} sind noch {{ $prevMonthOpenCount }} offene Posten. Der Übertrag in den nächsten Monat ist gesperrt.
        </div>
    @endif

    <div class="relative overflow-hidden rounded-xl border accent-box bg-gradient-to-br from-emerald-50 via-white to-emerald-100/70 dark:from-emerald-950/40 dark:via-slate-950 dark:to-emerald-900/30 shadow-sm">
        <div class="pointer-events-none absolute -left-12 -top-8 h-24 w-24 rounded-full bg-emerald-200/50 dark:bg-emerald-500/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -right-10 bottom-0 h-20 w-20 rounded-full bg-amber-200/40 dark:bg-amber-500/10 blur-2xl"></div>
        <div class="relative p-3">
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-emerald-800/80 dark:text-emerald-200/80">
                <div class="flex flex-wrap items-center gap-2">
                    @can('update', $month)
                        <button type="button" x-show="!editing" @click="editing = true; $nextTick(() => $refs.editName?.focus())" class="inline-flex items-center accent-icon hover:opacity-80" title="Monat bearbeiten" aria-label="Monat bearbeiten">
                            <x-icon-edit class="w-4 h-4" />
                        </button>
                    @endcan
                    <span class="text-xs font-semibold text-gray-900 dark:text-slate-100">{{ $month->name }}</span>
                    @if ($month->is_current)
                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800">Aktuell</span>
                    @endif
                    <span class="text-gray-400">•</span>
                    <span>{{ $month->date_from->format('d.m.Y') }} – {{ $month->date_to->format('d.m.Y') }}</span>
                    <span class="text-gray-400">·</span>
                    <span>Lebensunterhalt/Tag CHF {{ $fmt($month->daily_living_cost) }}</span>
                    <button type="button" class="inline-flex items-center gap-1 rounded-full border border-[var(--accent)] bg-white/80 dark:bg-slate-900/70 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-[var(--accent)] transition hover:opacity-80" @click="entriesOpen = true; $nextTick(() => $refs.entriesSection?.scrollIntoView({ behavior: 'smooth', block: 'start' }))">
                        Einträge
                    </button>
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
                </div>
            </div>
            @can('update', $month)
                <form x-show="editing" x-cloak x-ref="editForm" method="POST" action="{{ route('months.update', $month) }}" class="mt-2 flex flex-wrap items-end gap-2 text-xs">
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
            @if ($balanceAccounts->isNotEmpty())
                <div class="mt-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-600">
                        <div class="text-xs uppercase tracking-[0.2em] text-gray-500">Kontostände</div>
                        <div class="text-xs text-gray-500">Summe CHF {{ $fmt($balanceSum) }}</div>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($balanceAccounts as $account)
                            @php
                                $balance = $balanceAmounts[$account->id] ?? 0;
                                $balanceClass = $balance < 0 ? 'text-red-700' : 'text-gray-900';
                                $balanceInput = number_format((float) $balance, 2, '.', '');
                            @endphp
                            <div class="min-w-[12rem] rounded-lg border accent-box bg-white/80 dark:bg-slate-900/70 px-2 py-1">
                                <div class="text-xs uppercase tracking-wide text-gray-500">{{ $account->name }}</div>
                                <div class="mt-1 flex items-center justify-between gap-2">
                                    <div x-data="{ editing: false, value: '{{ $balanceInput }}' }" @click.outside="editing = false" class="w-full">
                                        <form method="POST" action="{{ route('months.balances.update', [$month, $account]) }}" class="flex items-center justify-between gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <button type="button" x-show="!editing" @click="editing = true; $nextTick(() => $refs.input.focus())" class="text-right tabular-nums font-semibold {{ $balanceClass }}">
                                                {{ $fmt($balance) }}
                                            </button>
                                            <input x-ref="input" x-show="editing" x-cloak type="number" step="0.01" name="amount" x-model="value" class="w-24 border border-gray-300 rounded px-2 py-1 text-sm text-right tabular-nums focus:border-[var(--accent)] focus:ring-[var(--accent)]" @keydown.enter.stop.prevent="$el.form.submit()" @keydown.escape="editing = false">
                                            <button x-show="editing" x-cloak type="submit" class="px-2 py-1 bg-[var(--accent)] text-white rounded text-xs">OK</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="mt-2 grid grid-cols-1 lg:grid-cols-[minmax(0,1fr),auto] items-center gap-3">
                <div class="space-y-1 w-full min-w-0">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs uppercase tracking-[0.2em] text-emerald-700/90">
                        <span>Monatsergebnis</span>
                        <form method="POST" action="{{ route('months.current', $month) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="is_current" value="{{ $includeBalanceInResult ? 0 : 1 }}">
                            <button type="submit" role="switch" aria-checked="{{ $includeBalanceInResult ? 'true' : 'false' }}" class="inline-flex items-center gap-2 rounded-full border border-emerald-200/70 bg-white/80 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 transition hover:opacity-80">
                                <span>Aktueller Monat</span>
                                <span class="{{ $includeBalanceInResult ? 'text-emerald-700' : 'text-gray-400' }}">{{ $includeBalanceInResult ? 'Ein' : 'Aus' }}</span>
                            </button>
                        </form>
                    </div>
                    <div class="text-[10px] uppercase tracking-[0.18em] text-gray-500">Kontostand zählt nur beim aktuellen Monat.</div>
                    <div class="w-full rounded-lg px-3 py-2 text-center text-2xl font-semibold tabular-nums {{ $monthResultBarClass }}">
                        CHF {{ $fmt($metrics['result'] ?? 0) }}
                    </div>
                </div>
                <div class="space-y-1 justify-self-end">
                    <div class="text-xs uppercase tracking-[0.2em] text-emerald-700/90">Kummuliert ab heute</div>
                    <div class="grid grid-cols-3 gap-2 max-w-[50vw]">
                        <div class="rounded-lg border accent-box bg-white/80 dark:bg-slate-900/70 px-2 py-1">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Resultat</div>
                            <div class="text-sm font-semibold tabular-nums {{ $resultTextClass }}">{{ $fmt($metrics['cumulative_result'] ?? 0) }}</div>
                        </div>
                        <div class="rounded-lg border accent-box bg-white/80 dark:bg-slate-900/70 px-2 py-1">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Arbeitstage</div>
                            <div class="text-sm font-semibold tabular-nums text-gray-900">{{ $metrics['cumulative_workdays'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-lg border accent-box bg-white/80 dark:bg-slate-900/70 px-2 py-1">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Umsatz/AT</div>
                            <div class="text-sm font-semibold tabular-nums {{ $resultTextClass }}">{{ $fmt($metrics['required_revenue_per_workday_from_today'] ?? 0) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        <x-section-table title="Einnahmen" sum="{{ $fmt($incomeSum) }}" bg-class="bg-green-50 dark:bg-emerald-950/30" x-data="{ moveMode: false, addExpected: false }">
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
                <tbody data-sortable data-order-url="{{ route('months.entries.order', $month) }}" data-type="income">
                    @if ($forecastAccounts->isNotEmpty())
                        <tr class="border-t border-green-200 text-xs uppercase tracking-wide text-green-900 dark:text-emerald-200">
                            <td class="pt-2 pb-1">
                                <div class="flex items-center gap-2">
                                    <span>Einnahmen erwartet</span>
                                    <button type="button" class="inline-flex h-5 w-5 items-center justify-center rounded border border-[var(--accent)] bg-white/80 text-xs font-semibold text-[var(--accent)] transition hover:text-[var(--accent)]" :class="addExpected ? 'border-[var(--accent)] bg-[var(--accent)] text-white' : ''" @click="addExpected = !addExpected" title="Erwartete Einnahme erfassen" aria-label="Erwartete Einnahme erfassen">
                                        +
                                    </button>
                                </div>
                            </td>
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
                                <div class="{{ $inputStackClass }}">
                                    <input type="text" name="description" form="{{ $incomeFormId }}" class="w-full border border-transparent bg-white/60 px-2 py-1 text-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" placeholder="Neue erwartete Einnahme" required>
                                    <select name="account_id" form="{{ $incomeFormId }}" class="border border-transparent bg-white/60 px-2 py-1 text-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                                        @foreach ($forecastAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                <div class="flex items-center justify-end gap-2">
                                    <input type="number" step="0.01" name="amount" form="{{ $incomeFormId }}" class="w-28 border border-transparent bg-white/60 px-2 py-1 text-sm text-right tabular-nums focus:border-[var(--accent)] focus:ring-[var(--accent)]" placeholder="0.00" required>
                                    <button type="submit" form="{{ $incomeFormId }}" class="px-2 py-1 bg-[var(--accent)] text-white rounded text-xs">+</button>
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
                        @endphp
                        <tr x-data="{
                            editing: false,
                            payOpen: false,
                            description: @js($income->description),
                            amount: @js($incomeAmountInput),
                            amountDisplay: @js($incomeAmountDisplay),
                            originalDescription: @js($income->description),
                            originalAmount: @js($incomeAmountInput),
                            originalAmountDisplay: @js($incomeAmountDisplay),
                            paymentAmount: @js($openAmount),
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
                                <input type="hidden" name="account_id" form="{{ $incomeEditId }}" value="{{ $income->account_id }}">
                                <input type="hidden" name="amount" form="{{ $incomeEditId }}" x-model="amount">
                                <div x-show="!editing" class="flex items-start gap-1">
                                    <button type="button" class="inline-flex items-center accent-icon hover:opacity-80" title="Bearbeiten" aria-label="Bearbeiten" @click="editing = true; payOpen = false; $nextTick(() => $refs.description.focus())">
                                        <x-icon-edit class="w-3 h-3" />
                                    </button>
                                    <span>{{ $income->description }}</span>
                                    @include('months.partials.carryover-badge', ['entry' => $income])
                                </div>
                                <input x-show="editing" x-cloak x-ref="description" type="text" name="description" form="{{ $incomeEditId }}" x-model="description" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:border-[var(--accent)] focus:ring-[var(--accent)]" @keydown.enter.stop.prevent="$el.form.submit()">
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
                                            <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-prev-month', $income) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-xs text-[var(--accent)] underline" title="Vorheriger Monat">←</button>
                                            </form>
                                            <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-next-month', $income) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-xs text-[var(--accent)] underline" title="Nächster Monat">→</button>
                                            </form>
                                            <span class="font-semibold">{{ $fmt($displayAmount) }}</span>
                                            @if ($openAmount > 0)
                                                <button type="button" class="inline-flex items-center text-[var(--accent)]" title="Zahlung eingegangen" aria-label="Zahlung eingegangen" @click="payOpen = !payOpen">
                                                    <x-icon-check class="w-3 h-3" />
                                                </button>
                                            @endif
                                        </div>
                                        <div x-show="editing" x-cloak class="flex items-center justify-end gap-2">
                                            <button type="submit" form="{{ $incomeEditId }}" class="{{ $editActionPrimary }}">OK</button>
                                            <button type="button" class="{{ $editActionGhost }}" @click="editing = false; description = originalDescription; amount = originalAmount; amountDisplay = originalAmountDisplay;">X</button>
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
                                    @if ($openAmount > 0)
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
                                description: @js($income->description),
                                amount: @js($incomeAmountInput),
                                amountDisplay: @js($incomeAmountDisplay),
                                originalDescription: @js($income->description),
                                originalAmount: @js($incomeAmountInput),
                                originalAmountDisplay: @js($incomeAmountDisplay),
                                paymentAmount: @js($openAmount),
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
                                    <input type="hidden" name="account_id" form="{{ $incomeEditId }}" value="{{ $income->account_id }}">
                                    <input type="hidden" name="amount" form="{{ $incomeEditId }}" x-model="amount">
                                    <div x-show="!editing" class="flex items-start gap-1">
                                        <button type="button" class="inline-flex items-center accent-icon hover:opacity-80" title="Bearbeiten" aria-label="Bearbeiten" @click="editing = true; payOpen = false; $nextTick(() => $refs.description.focus())">
                                            <x-icon-edit class="w-3 h-3" />
                                        </button>
                                        <span>{{ $income->description }}</span>
                                        @include('months.partials.carryover-badge', ['entry' => $income])
                                    </div>
                                    <input x-show="editing" x-cloak x-ref="description" type="text" name="description" form="{{ $incomeEditId }}" x-model="description" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:border-[var(--accent)] focus:ring-[var(--accent)]" @keydown.enter.stop.prevent="$el.form.submit()">
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
                                                <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-prev-month', $income) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="text-xs text-[var(--accent)] underline" title="Vorheriger Monat">←</button>
                                                </form>
                                                <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-next-month', $income) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="text-xs text-[var(--accent)] underline" title="Nächster Monat">→</button>
                                                </form>
                                                <span class="font-semibold">{{ $fmt($displayAmount) }}</span>
                                                @if ($openAmount > 0)
                                                    <button type="button" class="inline-flex items-center text-[var(--accent)]" title="Zahlung eingegangen" aria-label="Zahlung eingegangen" @click="payOpen = !payOpen">
                                                        <x-icon-check class="w-3 h-3" />
                                                    </button>
                                                @endif
                                            </div>
                                            <div x-show="editing" x-cloak class="flex items-center justify-end gap-2">
                                                <button type="submit" form="{{ $incomeEditId }}" class="{{ $editActionPrimary }}">OK</button>
                                                <button type="button" class="{{ $editActionGhost }}" @click="editing = false; description = originalDescription; amount = originalAmount; amountDisplay = originalAmountDisplay;">X</button>
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
                                        @if ($openAmount > 0)
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
                                    $incomeAmountInput = number_format((float) $income->amount, 2, '.', '');
                                    $incomeAmountDisplay = number_format((float) $income->amount, 2, '.', "'");
                                @endphp
                                <tr x-data="{
                                    editing: false,
                                    payOpen: false,
                                    description: @js($income->description),
                                    amount: @js($incomeAmountInput),
                                    amountDisplay: @js($incomeAmountDisplay),
                                    originalDescription: @js($income->description),
                                    originalAmount: @js($incomeAmountInput),
                                    originalAmountDisplay: @js($incomeAmountDisplay),
                                    paymentAmount: @js($income->open_amount),
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
                                        <input type="hidden" name="account_id" form="{{ $incomeEditId }}" value="{{ $income->account_id }}">
                                        <input type="hidden" name="amount" form="{{ $incomeEditId }}" x-model="amount">
                                        <div x-show="!editing" class="flex items-start gap-1">
                                            <button type="button" class="inline-flex items-center accent-icon hover:opacity-80" title="Bearbeiten" aria-label="Bearbeiten" @click="editing = true; payOpen = false; $nextTick(() => $refs.description.focus())">
                                                <x-icon-edit class="w-3 h-3" />
                                            </button>
                                            <span>{{ $income->description }}</span>
                                            @include('months.partials.carryover-badge', ['entry' => $income])
                                        </div>
                                        <input x-show="editing" x-cloak x-ref="description" type="text" name="description" form="{{ $incomeEditId }}" x-model="description" class="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:border-[var(--accent)] focus:ring-[var(--accent)]" @keydown.enter.stop.prevent="$el.form.submit()">
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
                                                    <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-prev-month', $income) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="text-xs text-[var(--accent)] underline" title="Vorheriger Monat">←</button>
                                                    </form>
                                                    <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-next-month', $income) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="text-xs text-[var(--accent)] underline" title="Nächster Monat">→</button>
                                                    </form>
                                                    <span class="font-semibold">{{ $fmt($income->open_amount) }}</span>
                                                    <button type="button" class="inline-flex items-center text-[var(--accent)]" title="Zahlung eingegangen" aria-label="Zahlung eingegangen" @click="payOpen = !payOpen">
                                                        <x-icon-check class="w-3 h-3" />
                                                    </button>
                                                </div>
                                                <div x-show="editing" x-cloak class="flex items-center justify-end gap-2">
                                                    <button type="submit" form="{{ $incomeEditId }}" class="{{ $editActionPrimary }}">OK</button>
                                                    <button type="button" class="{{ $editActionGhost }}" @click="editing = false; description = originalDescription; amount = originalAmount; amountDisplay = originalAmountDisplay;">X</button>
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
                            description: @js($expense->description),
                            amount: @js($expenseAmountInput),
                            amountDisplay: @js($expenseAmountDisplay),
                            originalDescription: @js($expense->description),
                            originalAmount: @js($expenseAmountInput),
                            originalAmountDisplay: @js($expenseAmountDisplay),
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
                                            <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-prev-month', $expense) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-xs text-[var(--accent)] underline" title="Vorheriger Monat">←</button>
                                            </form>
                                            <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-next-month', $expense) }}">
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
                    <tr class="border-t border-amber-200">
                        <td class="{{ $rowPadClass }} pr-2">{{ $livingLabel }}</td>
                        <td class="{{ $rowPadClass }} text-right tabular-nums font-semibold">{{ $fmt($metrics['living_cost_open']) }}</td>
                    </tr>

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
                            description: @js($fixcost->description),
                            amount: @js($fixcostAmountInput),
                            amountDisplay: @js($fixcostAmountDisplay),
                            originalDescription: @js($fixcost->description),
                            originalAmount: @js($fixcostAmountInput),
                            originalAmountDisplay: @js($fixcostAmountDisplay),
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
                                            <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-prev-month', $fixcost) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-xs text-[var(--accent)] underline" title="Vorheriger Monat">←</button>
                                            </form>
                                            <form x-show="moveMode" x-cloak method="POST" action="{{ route('entries.move-next-month', $fixcost) }}">
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
