<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonthRequest;
use App\Http\Requests\UpdateMonthRequest;
use App\Models\Account;
use App\Models\Entry;
use App\Models\Holiday;
use App\Models\Month;
use App\Models\RecurringTemplate;
use App\Services\AccountBalanceService;
use App\Services\MonthMetricsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MonthController extends Controller
{
    public function index(MonthMetricsService $metricsService)
    {
        $this->authorize('viewAny', Month::class);

        $user = request()->user();
        $showArchived = request()->boolean('show_archived');
        $monthsQuery = Month::forUser($user)->orderBy('date_from');
        if (! $showArchived) {
            $monthsQuery->visible();
        }
        $months = $monthsQuery->get();

        $holidays = collect();
        if ($months->isNotEmpty()) {
            $rangeStart = $months->first()->date_from;
            $rangeEnd = $months->last()->date_to;
            $holidays = Holiday::forUser($user)
                ->overlapping($rangeStart, $rangeEnd)
                ->orderBy('date_from')
                ->orderBy('date_to')
                ->get();
        }

        $monthCards = $months->map(function (Month $month) use ($metricsService, $holidays, $months) {
            $cumulative = $metricsService->cumulativeFromToday($month);
            $monthHolidays = $holidays
                ->filter(fn (Holiday $holiday) => $holiday->date_from->lte($month->date_to)
                    && $holiday->date_to->gte($month->date_from))
                ->values();
            $nextStart = $month->date_from->copy()->addMonthNoOverflow()->startOfMonth();
            $nextMonth = $months->first(fn (Month $candidate) => $candidate->date_from->eq($nextStart));
            $nextMonthHolidays = collect();
            if ($nextMonth) {
                $nextMonthHolidays = $holidays
                    ->filter(fn (Holiday $holiday) => $holiday->date_from->lte($nextMonth->date_to)
                        && $holiday->date_to->gte($nextMonth->date_from))
                    ->reject(fn (Holiday $holiday) => $holiday->date_from->lte($month->date_to)
                        && $holiday->date_to->gte($month->date_from))
                    ->values();
            }
            $combinedHolidays = $monthHolidays
                ->merge($nextMonthHolidays)
                ->unique('id')
                ->values();
            return [
                'month' => $month,
                'metrics' => $metricsService->calculate($month, null, $month->is_current),
                'cumulative' => $cumulative,
                'holidays' => $combinedHolidays,
            ];
        });

        return view('months.index', [
            'months' => $months,
            'monthCards' => $monthCards,
            'isSelfEmployed' => $user?->isSelfEmployed() ?? false,
            'showArchived' => $showArchived,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Month::class);

        $months = Month::forUser($request->user())
            ->visible()
            ->orderBy('date_from', 'desc')
            ->get();

        return view('months.create', [
            'months' => $months,
            'sourceMonthId' => $request->query('source_month_id'),
        ]);
    }

    public function store(StoreMonthRequest $request)
    {
        $user = $request->user();

        $month = Month::create([
            'user_id' => $user->id,
            'name' => $request->input('name'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'daily_living_cost' => $request->input('daily_living_cost'),
        ]);

        $sourceMonthId = $request->input('source_month_id');
        if ($sourceMonthId) {
            $sourceMonth = Month::forUser($user)->find($sourceMonthId);
            if ($sourceMonth) {
                $this->copyEntriesFromMonth($sourceMonth, $month);
            }
        }

        if ($request->boolean('import_templates', true)) {
            $this->importRecurringTemplates($month);
        }

        return redirect()
            ->route('months.show', $month)
            ->with('status', 'Monat erstellt.');
    }

    public function show(
        Request $request,
        Month $month,
        MonthMetricsService $metricsService,
        AccountBalanceService $balanceService
    )
    {
        $this->authorize('view', $month);

        $user = $request->user();
        $accounts = Account::forUser($user)->orderBy('name')->get();
        $entryFilters = $request->only(['type', 'status', 'account_id']);
        $entriesOpen = $request->boolean('entries');
        $includeBalanceInResult = $month->is_current;
        $boards = [
            $this->buildBoardData(
                $month,
                $user,
                $metricsService,
                $accounts,
                $balanceService,
                $entryFilters,
                $entriesOpen,
                $includeBalanceInResult
            ),
        ];

        return view('months.show', [
            'month' => $month,
            'boards' => $boards,
        ]);
    }

    public function edit(Month $month)
    {
        $this->authorize('update', $month);

        return view('months.edit', [
            'month' => $month,
        ]);
    }

    public function update(UpdateMonthRequest $request, Month $month)
    {
        $month->update($request->validated());

        return redirect()
            ->route('months.show', $month)
            ->with('status', 'Monat aktualisiert.');
    }

    public function destroy(Month $month)
    {
        $this->authorize('delete', $month);
        $month->delete();

        return redirect()
            ->route('months.index')
            ->with('status', 'Monat gelöscht.');
    }

    public function importTemplates(Month $month)
    {
        $this->authorize('update', $month);

        $count = $this->importRecurringTemplates($month);

        return back()->with('status', "Wiederkehrende Posten übernommen: {$count}.");
    }

    public function archive(Request $request, Month $month, MonthMetricsService $metricsService): RedirectResponse
    {
        $this->authorize('update', $month);

        $user = $request->user();
        if ($month->user_id !== $user->id) {
            abort(403);
        }

        if ($month->archived_at) {
            return back()->withErrors(['archive' => 'Monat ist bereits archiviert.']);
        }

        $baseMetrics = $metricsService->calculate($month, null, false);
        $result = (float) ($baseMetrics['result'] ?? 0);

        if (! $this->isArchiveEligible($month, $result)) {
            return back()->withErrors(['archive' => 'Monat kann nicht archiviert werden.']);
        }

        $month->archived_at = now();
        $month->save();

        $createdNextJanuary = false;
        if ($month->date_from->month === 1) {
            $nextStart = $month->date_from->copy()->addYear()->startOfMonth();
            $nextEnd = $nextStart->copy()->endOfMonth();

            $exists = Month::forUser($user)
                ->whereDate('date_from', $nextStart->toDateString())
                ->exists();

            if (! $exists) {
                $name = ucfirst(str_replace('.', '', $nextStart->locale(app()->getLocale())->translatedFormat('M Y')));

                $nextMonth = Month::create([
                    'user_id' => $user->id,
                    'name' => $name,
                    'date_from' => $nextStart->toDateString(),
                    'date_to' => $nextEnd->toDateString(),
                    'daily_living_cost' => $month->daily_living_cost,
                    'is_current' => false,
                ]);

                $this->importRecurringTemplates($nextMonth);
                $createdNextJanuary = true;
            }
        }

        $message = $createdNextJanuary
            ? 'Monat archiviert. Neuer Januar erstellt.'
            : 'Monat archiviert.';

        return redirect()
            ->route('months.index')
            ->with('status', $message);
    }

    public function createNext(Request $request)
    {
        $this->authorize('create', Month::class);

        $user = $request->user();
        $lastMonth = Month::forUser($user)
            ->visible()
            ->orderBy('date_from', 'desc')
            ->first();

        if (! $lastMonth) {
            return redirect()->route('months.create');
        }

        $nextStart = $lastMonth->date_from->copy()->addMonthNoOverflow()->startOfMonth();
        $nextEnd = $nextStart->copy()->endOfMonth();

        $exists = Month::forUser($user)
            ->whereDate('date_from', $nextStart->toDateString())
            ->exists();

        if ($exists) {
            return redirect()
                ->route('months.index')
                ->with('status', 'Der nächste Monat existiert bereits.');
        }

        $name = ucfirst(str_replace('.', '', $nextStart->locale(app()->getLocale())->translatedFormat('M Y')));

        $month = Month::create([
            'user_id' => $user->id,
            'name' => $name,
            'date_from' => $nextStart->toDateString(),
            'date_to' => $nextEnd->toDateString(),
            'daily_living_cost' => $lastMonth->daily_living_cost,
        ]);

        $this->importRecurringTemplates($month);

        return redirect()
            ->route('months.show', $month)
            ->with('status', 'Nächster Monat erstellt.');
    }

    public function setCurrent(Request $request, Month $month): RedirectResponse
    {
        $this->authorize('update', $month);

        $user = $request->user();
        if ($month->user_id !== $user->id) {
            abort(403);
        }

        $isCurrent = $request->boolean('is_current');

        if ($isCurrent) {
            Month::forUser($user)->update(['is_current' => false]);
            $month->is_current = true;
            $month->save();
        } else {
            $month->is_current = false;
            $month->save();
        }

        return back()->with('status', $isCurrent ? 'Monat als aktuell markiert.' : 'Aktuellen Monat aufgehoben.');
    }

    public function rolloverOpenEntries(Request $request, Month $month): RedirectResponse
    {
        $this->authorize('update', $month);

        $user = $request->user();
        if ($month->user_id !== $user->id) {
            abort(403);
        }

        if (! $month->is_current) {
            return back()->withErrors(['rollover' => 'Nur der aktuelle Monat kann übertragen werden.']);
        }

        $previousStart = $month->date_from->copy()->subMonthNoOverflow()->startOfMonth();
        $previousMonth = Month::forUser($user)
            ->visible()
            ->whereDate('date_from', $previousStart->toDateString())
            ->first();

        if ($previousMonth) {
            $openPrevCount = Entry::query()
                ->where('user_id', $user->id)
                ->where('month_id', $previousMonth->id)
                ->whereIn('type', ['income', 'expense', 'fixcost'])
                ->where('status', '!=', 'paid')
                ->count();

            if ($openPrevCount > 0) {
                return back()->withErrors(['rollover' => 'Im Vormonat sind noch offene Posten. Bitte zuerst abschliessen.']);
            }
        }

        $targetStart = $month->date_from->copy()->addMonthNoOverflow()->startOfMonth();
        $targetMonth = Month::forUser($user)
            ->visible()
            ->whereDate('date_from', $targetStart->toDateString())
            ->first();

        if (! $targetMonth) {
            return back()->withErrors(['rollover' => 'Zielmonat nicht vorhanden.']);
        }

        $entries = Entry::query()
            ->where('user_id', $user->id)
            ->where('month_id', $month->id)
            ->whereIn('type', ['income', 'expense', 'fixcost'])
            ->where('status', '!=', 'paid')
            ->get();

        $moved = 0;

        if ($entries->isNotEmpty()) {
            $entriesByType = $entries->groupBy('type');

            foreach ($entriesByType as $type => $group) {
                $sorted = $group->sortBy(function (Entry $entry) {
                    return $entry->due_date?->timestamp ?? $entry->entry_date?->timestamp ?? 0;
                })->values();

                $existingQuery = Entry::query()
                    ->where('month_id', $targetMonth->id)
                    ->where('type', $type)
                    ->whereNotNull('sort_order');

                if ($existingQuery->exists()) {
                    $existingQuery->increment('sort_order', $sorted->count());
                }

                $startOrder = 1;

                foreach ($sorted as $entry) {
                    $update = [
                        'month_id' => $targetMonth->id,
                        'sort_order' => $startOrder++,
                        'moved_from_month_id' => $month->id,
                    ];

                    if ($entry->type === 'expense') {
                        $baseDate = $entry->due_date ?? $entry->entry_date;
                        $update['due_date'] = $baseDate->copy()->addMonthNoOverflow()->toDateString();
                    } else {
                        $update['entry_date'] = $targetMonth->date_from->toDateString();
                    }

                    if (! $entry->origin_month_id) {
                        $update['origin_month_id'] = $entry->moved_from_month_id ?? $month->id;
                    }

                    $entry->update($update);
                    $moved++;
                }
            }
        }

        Month::forUser($user)->update(['is_current' => false]);
        $targetMonth->is_current = true;
        $targetMonth->save();

        if ($moved === 0) {
            return back()->with('status', "Keine offenen Posten. Aktueller Monat ist jetzt {$targetMonth->name}.");
        }

        return back()->with('status', "{$moved} offene Posten nach {$targetMonth->name} übertragen. Aktueller Monat aktualisiert.");
    }

    public function revertRollover(Request $request, Month $month): RedirectResponse
    {
        $this->authorize('update', $month);

        $user = $request->user();
        if ($month->user_id !== $user->id) {
            abort(403);
        }

        if (! $month->is_current) {
            return back()->withErrors(['rollover' => 'Nur der aktuelle Monat kann zurückgesetzt werden.']);
        }

        $previousStart = $month->date_from->copy()->subMonthNoOverflow()->startOfMonth();
        $previousMonth = Month::forUser($user)
            ->visible()
            ->whereDate('date_from', $previousStart->toDateString())
            ->first();

        if (! $previousMonth) {
            return back()->withErrors(['rollover' => 'Vormonat nicht vorhanden.']);
        }

        $entries = Entry::query()
            ->where('user_id', $user->id)
            ->where('month_id', $month->id)
            ->whereIn('type', ['income', 'expense', 'fixcost'])
            ->where('moved_from_month_id', $previousMonth->id)
            ->get();

        if ($entries->isEmpty()) {
            return back()->with('status', 'Keine verschobenen Posten gefunden.');
        }

        $moved = 0;
        $entriesByType = $entries->groupBy('type');

        foreach ($entriesByType as $type => $group) {
            $sorted = $group->sortBy(function (Entry $entry) {
                return $entry->due_date?->timestamp ?? $entry->entry_date?->timestamp ?? 0;
            })->values();

            $maxOrder = Entry::query()
                ->where('month_id', $previousMonth->id)
                ->where('type', $type)
                ->max('sort_order');

            $startOrder = is_null($maxOrder) ? 1 : $maxOrder + 1;

            foreach ($sorted as $entry) {
                $update = [
                    'month_id' => $previousMonth->id,
                    'sort_order' => $startOrder++,
                    'moved_from_month_id' => null,
                ];

                if ($entry->origin_month_id === $previousMonth->id) {
                    $update['origin_month_id'] = null;
                }

                if ($entry->type === 'expense') {
                    $baseDate = $entry->due_date ?? $entry->entry_date ?? $month->date_from;
                    $update['due_date'] = $baseDate->copy()->subMonthNoOverflow()->toDateString();
                } else {
                    $update['entry_date'] = $previousMonth->date_from->toDateString();
                }

                $entry->update($update);
                $moved++;
            }
        }

        Month::forUser($user)->update(['is_current' => false]);
        $previousMonth->is_current = true;
        $previousMonth->save();

        return back()->with('status', "{$moved} Posten nach {$previousMonth->name} zurückgesetzt. Aktueller Monat aktualisiert.");
    }

    private function isArchiveEligible(Month $month, float $result): bool
    {
        if ($month->archived_at) {
            return false;
        }

        if ($month->is_current) {
            return false;
        }

        $today = now()->startOfDay();
        if (! $month->date_to->lt($today)) {
            return false;
        }

        return abs(round($result, 2)) < 0.01;
    }

    private function copyEntriesFromMonth(Month $sourceMonth, Month $targetMonth): void
    {
        $entries = $sourceMonth->entries()
            ->where('type', '!=', 'transfer')
            ->get();

        foreach ($entries as $entry) {
            $dueDate = null;
            if ($entry->type === 'expense') {
                $baseDate = $entry->due_date ?? $entry->entry_date ?? $sourceMonth->date_from;
                $day = $baseDate->day;
                $dueDate = $targetMonth->date_from->copy()
                    ->day(min($day, $targetMonth->date_from->daysInMonth))
                    ->toDateString();
            }

            Entry::create([
                'user_id' => $targetMonth->user_id,
                'month_id' => $targetMonth->id,
                'entry_date' => $targetMonth->date_from->toDateString(),
                'due_date' => $dueDate,
                'type' => $entry->type,
                'income_source' => $entry->income_source,
                'direction' => $entry->direction,
                'amount' => $entry->amount,
                'account_id' => $entry->account_id,
                'status' => 'open',
                'description' => $entry->description,
                'recurring_template_id' => $entry->recurring_template_id,
                'sort_order' => $entry->sort_order,
            ]);
        }
    }

    private function importRecurringTemplates(Month $month): int
    {
        $user = $month->user;
        $accounts = $user->accounts()->get()->groupBy('type');
        $templates = RecurringTemplate::forUser($user)
            ->where('is_active', true)
            ->whereIn('kind', ['income', 'fixcost'])
            ->get();

        $orderCounters = Entry::query()
            ->where('month_id', $month->id)
            ->select('type')
            ->selectRaw('MAX(sort_order) as max_order')
            ->groupBy('type')
            ->pluck('max_order', 'type')
            ->mapWithKeys(static fn ($value, $key) => [$key => $value ?? 0])
            ->all();

        $created = 0;

        foreach ($templates as $template) {
            if ($template->remaining_amount !== null && (float) $template->remaining_amount <= 0) {
                continue;
            }

            if (! $template->appliesToMonth($month)) {
                continue;
            }

            $exists = Entry::query()
                ->where('month_id', $month->id)
                ->where(function ($query) use ($template) {
                    $query->where('recurring_template_id', $template->id)
                        ->orWhere(function ($query) use ($template) {
                            $query->where('type', $template->kind)
                                ->where('description', $template->name)
                                ->where('amount', $template->amount);
                        });
                })
                ->exists();

            if ($exists) {
                continue;
            }

            $account = $template->defaultAccount;
            if ($template->kind === 'income' && $account && ! in_array($account->type, ['forecast', 'clearing'], true)) {
                $account = null;
            }
            $account = $account
                ?? ($template->kind === 'income'
                    ? ($accounts->get('forecast')?->first() ?? $accounts->get('clearing')?->first())
                    : $accounts->get('ist')?->first())
                ?? $user->accounts()->first();

            if (! $account) {
                continue;
            }

            $amount = (float) $template->amount;
            if ($template->remaining_amount !== null) {
                $amount = min($amount, (float) $template->remaining_amount);
            }

            Entry::create([
                'user_id' => $user->id,
                'month_id' => $month->id,
                'entry_date' => $month->date_from->toDateString(),
                'due_date' => $template->kind === 'expense' ? $month->date_to->toDateString() : null,
                'type' => $template->kind,
                'income_source' => $template->kind === 'income' ? 'manual' : null,
                'direction' => $template->kind === 'income' ? 'in' : 'out',
                'amount' => $amount,
                'account_id' => $account->id,
                'status' => 'open',
                'description' => $template->name,
                'recurring_template_id' => $template->id,
                'sort_order' => $orderCounters[$template->kind] = ($orderCounters[$template->kind] ?? 0) + 1,
            ]);

            $created++;
        }

        return $created;
    }

    private function buildBoardData(
        Month $month,
        $user,
        MonthMetricsService $metricsService,
        $accounts,
        AccountBalanceService $balanceService,
        array $entryFilters = [],
        bool $entriesOpen = false,
        bool $includeBalanceInResult = false
    ): array {
        $entries = $month->entries()
            ->where('user_id', $user->id)
            ->with(['account', 'relatedEntry', 'relatedTransfersOut', 'recurringTemplate', 'movedFromMonth', 'originMonth'])
            ->orderByRaw('sort_order is null')
            ->orderBy('sort_order')
            ->orderBy('entry_date')
            ->get();

        $metricVariants = $metricsService->calculateVariants($month);
        $metrics = $includeBalanceInResult
            ? $metricVariants['with_balance']
            : $metricVariants['without_balance'];
        $archiveEligible = $this->isArchiveEligible(
            $month,
            (float) ($metricVariants['without_balance']['result'] ?? 0)
        );
        $cumulative = $metricsService->cumulativeFromToday($month);
        $metrics['cumulative_result'] = $cumulative['result_sum'];
        $metrics['cumulative_workdays'] = $cumulative['workdays_sum'];
        $metrics['required_revenue_per_workday_from_today'] = $cumulative['required_per_workday'];
        $metrics['include_balance_in_result'] = $includeBalanceInResult;

        $forecastBalances = Entry::query()
            ->where('user_id', $user->id)
            ->where('month_id', $month->id)
            ->where('type', 'income')
            ->where('direction', 'in')
            ->whereIn('status', ['open', 'partial'])
            ->whereNull('recurring_template_id')
            ->where(function ($query) {
                $query->where('income_source', 'expected')
                    ->orWhere(function ($query) {
                        $query->whereNull('income_source');
                    });
            })
            ->whereHas('account', static function ($query) {
                $query->whereIn('type', ['forecast', 'clearing']);
            })
            ->with('relatedTransfersOut')
            ->get()
            ->groupBy('account_id')
            ->map(static fn ($entries) => round($entries->sum(static fn (Entry $entry) => $entry->open_amount), 2));

        $balanceAccounts = $accounts->where('type', 'ist');
        $balanceMeta = $balanceService->balanceMetaForMonth($month, $balanceAccounts);
        $accountBalances = collect($balanceMeta)->mapWithKeys(static function ($meta, $accountId) {
            return [$accountId => $meta['effective']];
        });
        $filteredEntries = $entries;
        if (! empty($entryFilters['type'])) {
            $filteredEntries = $filteredEntries->where('type', $entryFilters['type']);
        }
        if (! empty($entryFilters['status'])) {
            $filteredEntries = $filteredEntries->where('status', $entryFilters['status']);
        }
        if (! empty($entryFilters['account_id'])) {
            $filteredEntries = $filteredEntries->where('account_id', (int) $entryFilters['account_id']);
        }
        $hasFilters = collect($entryFilters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();
        $entriesOpen = $entriesOpen || $hasFilters;

        $nextMonth = Month::forUser($user)
            ->visible()
            ->whereDate(
                'date_from',
                $month->date_from->copy()->addMonthNoOverflow()->startOfMonth()->toDateString()
            )
            ->first();

        $prevMonth = Month::forUser($user)
            ->visible()
            ->whereDate(
                'date_from',
                $month->date_from->copy()->subMonthNoOverflow()->startOfMonth()->toDateString()
            )
            ->first();

        $prevMonthOpenCount = 0;
        if ($prevMonth) {
            $prevMonthOpenCount = Entry::query()
                ->where('user_id', $user->id)
                ->where('month_id', $prevMonth->id)
                ->whereIn('type', ['income', 'expense', 'fixcost'])
                ->where('status', '!=', 'paid')
                ->count();
        }

        $revertCount = 0;
        if ($month->is_current && $prevMonth) {
            $revertCount = $entries
                ->whereIn('type', ['income', 'expense', 'fixcost'])
                ->where('moved_from_month_id', $prevMonth->id)
                ->count();
        }

        $holidays = Holiday::forUser($user)
            ->overlapping($month->date_from, $month->date_to)
            ->orderBy('date_from')
            ->orderBy('date_to')
            ->get();
        $nextMonthHolidays = collect();
        if ($nextMonth) {
            $nextMonthHolidays = Holiday::forUser($user)
                ->overlapping($nextMonth->date_from, $nextMonth->date_to)
                ->orderBy('date_from')
                ->orderBy('date_to')
                ->get()
                ->reject(function (Holiday $holiday) use ($month) {
                    return $holiday->date_from->lte($month->date_to)
                        && $holiday->date_to->gte($month->date_from);
                })
                ->values();
        }

        return [
            'month' => $month,
            'entries' => $entries,
            'entriesList' => $filteredEntries,
            'entryFilters' => $entryFilters,
            'entriesOpen' => $entriesOpen,
            'metrics' => $metrics,
            'accounts' => $accounts,
            'accountBalances' => $accountBalances,
            'balanceMeta' => $balanceMeta,
            'forecastBalances' => $forecastBalances,
            'nextMonth' => $nextMonth,
            'canRollover' => $month->is_current && $nextMonth && $prevMonthOpenCount === 0,
            'prevMonth' => $prevMonth,
            'prevMonthOpenCount' => $prevMonthOpenCount,
            'revertCount' => $revertCount,
            'canRevert' => $revertCount > 0,
            'pendingTemplates' => $this->countPendingTemplates($month),
            'canArchive' => $archiveEligible,
            'holidays' => $holidays,
            'nextMonthHolidays' => $nextMonthHolidays,
        ];
    }

    private function countPendingTemplates(Month $month): int
    {
        $today = now()->startOfDay();
        if ($month->date_to->lt($today)) {
            return 0;
        }

        $user = $month->user;
        $templates = RecurringTemplate::forUser($user)
            ->where('is_active', true)
            ->whereIn('kind', ['income', 'fixcost'])
            ->get();

        if ($templates->isEmpty()) {
            return 0;
        }

        $existingTemplateIds = Entry::query()
            ->where('month_id', $month->id)
            ->whereNotNull('recurring_template_id')
            ->pluck('recurring_template_id')
            ->unique();

        $existingEntries = Entry::query()
            ->where('month_id', $month->id)
            ->whereIn('type', $templates->pluck('kind')->unique())
            ->get(['type', 'description', 'amount']);

        $existingFallback = [];
        foreach ($existingEntries as $entry) {
            $existingFallback[$this->templateKey($entry->type, $entry->description, $entry->amount)] = true;
        }

        $pending = 0;
        foreach ($templates as $template) {
            if ($template->remaining_amount !== null && (float) $template->remaining_amount <= 0) {
                continue;
            }

            if (! $template->appliesToMonth($month)) {
                continue;
            }

            if ($existingTemplateIds->contains($template->id)) {
                continue;
            }

            $key = $this->templateKey($template->kind, $template->name, $template->amount);
            if (isset($existingFallback[$key])) {
                continue;
            }

            $pending++;
        }

        return $pending;
    }

    private function templateKey(string $type, string $description, $amount): string
    {
        return $type.'|'.$description.'|'.number_format((float) $amount, 2, '.', '');
    }
}
