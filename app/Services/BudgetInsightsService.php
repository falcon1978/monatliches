<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\Month;
use App\Models\User;
use App\Services\Ai\AiClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BudgetInsightsService
{
    private const CACHE_TTL_MINUTES = 10;

    public function __construct(
        private MonthMetricsService $metricsService,
        private AiClient $aiClient
    ) {
    }

    public function analyzeMonth(Month $month, User $user): array
    {
        $this->assertUserScope($month, $user);

        $cacheKey = $this->cacheKey($month, $user);

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($month, $user) {
            return $this->buildInsights($month, $user);
        });
    }

    public function refreshMonth(Month $month, User $user): array
    {
        $this->assertUserScope($month, $user);

        $cacheKey = $this->cacheKey($month, $user);
        $result = $this->buildInsights($month, $user);

        Cache::put($cacheKey, $result, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $result;
    }

    private function buildInsights(Month $month, User $user): array
    {
        $month = Month::forUser($user)->findOrFail($month->id);

        $context = $this->buildContext($month, $user);
        $findings = $this->buildDeterministicFindings($context);
        $fallback = $this->buildFallbackResult($month, $context, $findings);

        $payload = [
            'tenant_id' => $this->tenantId($user),
            'rapport_id' => sprintf('month-%d-%s', $month->id, now()->format('YmdHi')),
            'rapport_summary' => $context['rapport_summary'],
            'findings' => $findings,
        ];

        $response = $this->aiClient->post('/v1/rapport/analyze', $payload);
        if (! ($response['ok'] ?? false)) {
            return $fallback;
        }

        $aiData = $response['data'] ?? [];
        if (! is_array($aiData)) {
            return $fallback;
        }

        $normalized = $this->normalizeAiResult($aiData, $month, $context, $findings);
        if (! $normalized) {
            return $fallback;
        }

        return $normalized;
    }

    private function buildContext(Month $month, User $user): array
    {
        $today = now()->startOfDay();
        $metricVariants = $this->metricsService->calculateVariants($month);
        $metrics = $month->is_current ? $metricVariants['with_balance'] : $metricVariants['without_balance'];
        $cumulative = $this->metricsService->cumulativeFromToday($month);
        $metrics['cumulative_result'] = (float) ($cumulative['result_sum'] ?? 0);
        $metrics['cumulative_workdays'] = (int) ($cumulative['workdays_sum'] ?? 0);
        $metrics['required_revenue_per_workday_from_today'] = (float) ($cumulative['required_per_workday'] ?? 0);

        $openEntries = Entry::query()
            ->where('user_id', $user->id)
            ->where('month_id', $month->id)
            ->whereIn('type', ['income', 'expense', 'fixcost'])
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->where('type', 'income')
                        ->whereIn('status', ['open', 'partial']);
                })->orWhere(function ($query) {
                    $query->whereIn('type', ['expense', 'fixcost'])
                        ->where('status', '!=', 'paid');
                });
            })
            ->with(['account:id,type,name', 'relatedTransfersOut:id,related_entry_id,amount,type,direction'])
            ->orderBy('due_date')
            ->orderBy('entry_date')
            ->get();

        $incomeItems = $openEntries
            ->where('type', 'income')
            ->values()
            ->map(function (Entry $entry) {
                $source = $this->resolveIncomeSource($entry);

                return [
                    'id' => $entry->id,
                    'description' => (string) $entry->description,
                    'open_amount' => $this->roundMoney((float) $entry->open_amount),
                    'status' => (string) $entry->status,
                    'income_source' => $source,
                    'account_type' => (string) ($entry->account?->type ?? ''),
                    'due_date' => $entry->entry_date?->toDateString(),
                ];
            })
            ->values();

        $outgoingItems = $openEntries
            ->whereIn('type', ['expense', 'fixcost'])
            ->values()
            ->map(function (Entry $entry) use ($month, $today) {
                $dueDate = $this->resolveDueDate($entry, $month);

                return [
                    'id' => $entry->id,
                    'type' => (string) $entry->type,
                    'description' => (string) $entry->description,
                    'amount' => $this->roundMoney((float) $entry->amount),
                    'status' => (string) $entry->status,
                    'due_date' => $dueDate->toDateString(),
                    'account_type' => (string) ($entry->account?->type ?? ''),
                    'is_overdue' => $dueDate->lt($today),
                ];
            })
            ->values();

        $overdueOutgoingItems = $outgoingItems
            ->where('is_overdue', true)
            ->sortBy('due_date')
            ->values();
        $overdueAmount = $this->roundMoney((float) $overdueOutgoingItems->sum('amount'));
        $outgoingOpenTotal = $this->roundMoney(
            (float) ($metrics['open_expenses'] ?? 0) + (float) ($metrics['living_cost_open'] ?? 0)
        );
        $expectedIncomeOpenTotal = $this->roundMoney(
            (float) $incomeItems->where('income_source', 'expected')->sum('open_amount')
        );

        $topExpenses = $this->topExpenses($outgoingItems, (float) ($metrics['living_cost_open'] ?? 0), $outgoingOpenTotal);
        $upcomingDueBuckets = $this->upcomingDueBuckets($outgoingItems, $today);

        $historyResults = $this->historyResults($month, $user);
        $previousAverage = $historyResults->take(3)->avg('result');
        $previousAverage = $previousAverage === null ? null : $this->roundMoney((float) $previousAverage);
        $currentResult = $this->roundMoney((float) ($metrics['result'] ?? 0));
        $deltaToPreviousAverage = $previousAverage === null
            ? null
            : $this->roundMoney($currentResult - $previousAverage);

        $rapportSummary = [
            'output_language' => 'de',
            'target_audience' => 'laie',
            'style_guide' => 'Klar, konkret und ohne Fachjargon.',
            'month' => [
                'id' => $month->id,
                'name' => (string) $month->name,
                'date_from' => $month->date_from->toDateString(),
                'date_to' => $month->date_to->toDateString(),
                'is_current' => (bool) $month->is_current,
            ],
            'metrics' => [
                'income_total' => $this->roundMoney((float) ($metrics['income_total'] ?? 0)),
                'open_expenses' => $this->roundMoney((float) ($metrics['open_expenses'] ?? 0)),
                'living_cost_open' => $this->roundMoney((float) ($metrics['living_cost_open'] ?? 0)),
                'result' => $currentResult,
                'cumulative_result' => $this->roundMoney((float) ($metrics['cumulative_result'] ?? 0)),
                'open_forecast_income' => $this->roundMoney((float) ($metrics['open_forecast_income'] ?? 0)),
                'non_customer_income' => $this->roundMoney((float) ($metrics['non_customer_income'] ?? 0)),
                'balance_income' => $this->roundMoney((float) ($metricVariants['with_balance']['balance_income'] ?? 0)),
                'workdays_remaining' => (int) ($metrics['workdays_remaining'] ?? 0),
                'cumulative_workdays' => (int) ($metrics['cumulative_workdays'] ?? 0),
                'required_revenue_per_workday' => $this->roundMoney((float) ($metrics['required_revenue_per_workday'] ?? 0)),
                'required_revenue_per_workday_from_today' => $this->roundMoney((float) ($metrics['required_revenue_per_workday_from_today'] ?? 0)),
                'outgoing_open_total' => $outgoingOpenTotal,
                'overdue_open_total' => $overdueAmount,
            ],
            'open_items' => [
                'count_total' => (int) ($incomeItems->count() + $outgoingItems->count()),
                'count_income' => (int) $incomeItems->count(),
                'count_expense_fixcost' => (int) $outgoingItems->count(),
                'count_overdue_expense_fixcost' => (int) $overdueOutgoingItems->count(),
            ],
            'top_expenses' => $topExpenses->values()->all(),
            'upcoming_due_buckets' => $upcomingDueBuckets,
            'month_over_month' => [
                'history_months' => $historyResults->take(4)->values()->all(),
                'previous_average_result' => $previousAverage,
                'delta_to_previous_average' => $deltaToPreviousAverage,
            ],
        ];

        return [
            'today' => $today,
            'metrics' => $metrics,
            'metrics_with_balance' => $metricVariants['with_balance'],
            'outgoing_open_total' => $outgoingOpenTotal,
            'expected_income_open_total' => $expectedIncomeOpenTotal,
            'income_open_total' => $this->roundMoney((float) $incomeItems->sum('open_amount')),
            'income_items' => $incomeItems,
            'outgoing_items' => $outgoingItems,
            'overdue_outgoing_items' => $overdueOutgoingItems,
            'top_expenses' => $topExpenses,
            'upcoming_due_buckets' => collect($upcomingDueBuckets),
            'history_results' => $historyResults,
            'previous_average_result' => $previousAverage,
            'delta_to_previous_average' => $deltaToPreviousAverage,
            'rapport_summary' => $rapportSummary,
        ];
    }

    private function buildDeterministicFindings(array $context): array
    {
        $findings = [];
        $metrics = $context['metrics'];
        $metricsWithBalance = $context['metrics_with_balance'];
        $result = (float) ($metrics['result'] ?? 0);
        $incomeTotal = max(0.0, (float) ($metrics['income_total'] ?? 0));
        $outgoingOpenTotal = max(0.0, (float) ($context['outgoing_open_total'] ?? 0));
        $expectedIncomeOpenTotal = max(0.0, (float) ($context['expected_income_open_total'] ?? 0));
        $openItemsCount = (int) ($context['income_items']->count() + $context['outgoing_items']->count());

        if ($result < 0) {
            $gapToZero = abs($result);
            $severity = $gapToZero >= $this->threshold('result_negative_high_gap', 300.0)
                ? 'high'
                : 'medium';

            $findings[] = $this->makeFinding(
                'result_negative',
                $severity,
                [
                    'result' => $this->roundMoney($result),
                    'gap_to_zero' => $this->roundMoney($gapToZero),
                ],
                ['metrics.result'],
                $this->fixesFromTopExpenses($context['top_expenses'], 2)
            );
        }

        if ($incomeTotal > 0) {
            $expensePressure = $outgoingOpenTotal / $incomeTotal;
            if ($expensePressure >= $this->threshold('expense_pressure_ratio', 0.85)) {
                $severity = $expensePressure >= 1.0 ? 'high' : 'medium';

                $findings[] = $this->makeFinding(
                    'expense_pressure_high',
                    $severity,
                    [
                        'expense_pressure_ratio' => round($expensePressure, 4),
                        'expense_pressure_percent' => round($expensePressure * 100, 1),
                        'outgoing_open_total' => $this->roundMoney($outgoingOpenTotal),
                        'income_total' => $this->roundMoney($incomeTotal),
                    ],
                    ['metrics.open_expenses', 'metrics.living_cost_open', 'metrics.income_total'],
                    $this->fixesFromTopExpenses($context['top_expenses'], 3)
                );
            }
        }

        if ($incomeTotal > 0) {
            $dependency = $expectedIncomeOpenTotal / $incomeTotal;
            if ($dependency >= $this->threshold('expected_income_dependency_ratio', 0.55)) {
                $severity = $dependency >= $this->threshold('expected_income_dependency_high_ratio', 0.75)
                    ? 'high'
                    : 'medium';

                $expectedDueSoon = $context['income_items']
                    ->where('income_source', 'expected')
                    ->filter(function (array $item) use ($context) {
                        if (empty($item['due_date'])) {
                            return false;
                        }

                        $dueDate = Carbon::parse($item['due_date'])->startOfDay();

                        return $dueDate->lte($context['today']->copy()->addDays(14));
                    });

                $findings[] = $this->makeFinding(
                    'expected_income_dependency_high',
                    $severity,
                    [
                        'dependency_ratio' => round($dependency, 4),
                        'dependency_percent' => round($dependency * 100, 1),
                        'expected_income_open_total' => $this->roundMoney($expectedIncomeOpenTotal),
                        'expected_income_due_14_days' => $this->roundMoney((float) $expectedDueSoon->sum('open_amount')),
                    ],
                    ['metrics.open_forecast_income', 'metrics.income_total'],
                    [
                        $this->makeFix(
                            'Unsichere Einnahmen frueh pruefen',
                            'Pruefe zuerst erwartete Einnahmen mit nahem Termin und hake aktiv nach.',
                            (float) $expectedDueSoon->sum('open_amount')
                        ),
                    ]
                );
            }
        }

        $overdueItems = $context['overdue_outgoing_items'];
        if ($overdueItems->isNotEmpty()) {
            $overdueAmount = (float) $overdueItems->sum('amount');
            $severity = $overdueAmount >= $this->threshold('overdue_high_amount', 400.0) || $overdueItems->count() >= 3
                ? 'high'
                : 'medium';

            $oldestDueDate = (string) ($overdueItems->first()['due_date'] ?? '');

            $findings[] = $this->makeFinding(
                'overdue_expenses_present',
                $severity,
                [
                    'overdue_count' => $overdueItems->count(),
                    'overdue_amount' => $this->roundMoney($overdueAmount),
                    'oldest_due_date' => $oldestDueDate,
                ],
                ['upcoming_due_buckets', 'metrics.overdue_open_total'],
                [
                    $this->makeFix(
                        'Ueberfaellige Posten zuerst schliessen',
                        sprintf('Beginne mit den aeltesten offenen Ausgaben (aktuell %d Posten).', $overdueItems->count()),
                        $overdueAmount
                    ),
                ]
            );
        }

        $largestExpense = $context['top_expenses']->first();
        if (is_array($largestExpense) && $outgoingOpenTotal > 0) {
            $share = (float) ($largestExpense['share_of_open_costs'] ?? 0);
            if ($share >= $this->threshold('single_expense_concentration_ratio', 0.35)) {
                $severity = $share >= $this->threshold('single_expense_concentration_high_ratio', 0.5)
                    ? 'high'
                    : 'medium';
                $amount = (float) ($largestExpense['amount'] ?? 0);

                $findings[] = $this->makeFinding(
                    'single_expense_concentration',
                    $severity,
                    [
                        'expense_label' => (string) ($largestExpense['label'] ?? 'Groesster Posten'),
                        'amount' => $this->roundMoney($amount),
                        'share_percent' => round($share * 100, 1),
                    ],
                    ['top_expenses.0'],
                    [
                        $this->makeFix(
                            'Groessten Einzelposten priorisieren',
                            sprintf(
                                '"%s" bindet %.1f%% deiner offenen Kosten.',
                                (string) ($largestExpense['label'] ?? 'Groesster Posten'),
                                $share * 100
                            ),
                            $this->roundMoney($amount * 0.1),
                            round($share * 100, 1)
                        ),
                    ]
                );
            }
        }

        if ($outgoingOpenTotal > 0) {
            $buffer = max(0.0, (float) ($metricsWithBalance['balance_income'] ?? 0));
            $bufferRatio = $buffer / $outgoingOpenTotal;
            if ($bufferRatio < $this->threshold('low_buffer_ratio', 0.25)) {
                $severity = $bufferRatio < $this->threshold('low_buffer_high_ratio', 0.1)
                    ? 'high'
                    : 'medium';
                $targetBuffer = $this->roundMoney($outgoingOpenTotal * 0.25);
                $bufferGap = max(0.0, $targetBuffer - $buffer);

                $findings[] = $this->makeFinding(
                    'low_buffer_vs_open_items',
                    $severity,
                    [
                        'buffer_total' => $this->roundMoney($buffer),
                        'buffer_ratio_percent' => round($bufferRatio * 100, 1),
                        'outgoing_open_total' => $this->roundMoney($outgoingOpenTotal),
                        'buffer_gap_to_25_percent' => $this->roundMoney($bufferGap),
                    ],
                    ['metrics.balance_income', 'metrics.outgoing_open_total'],
                    [
                        $this->makeFix(
                            'Liquiditaetspuffer aufbauen',
                            'Plane zuerst einen festen Puffer, bevor weitere variable Ausgaben entstehen.',
                            $bufferGap
                        ),
                    ]
                );
            }
        }

        $previousAverage = $context['previous_average_result'];
        if ($previousAverage !== null) {
            $delta = (float) ($context['delta_to_previous_average'] ?? 0);
            if ($delta <= -1 * $this->threshold('mom_deterioration_abs', 150.0)) {
                $severity = $delta <= -1 * $this->threshold('mom_deterioration_high_abs', 350.0)
                    ? 'high'
                    : 'medium';

                $findings[] = $this->makeFinding(
                    'month_over_month_deterioration',
                    $severity,
                    [
                        'current_result' => $this->roundMoney((float) ($metrics['result'] ?? 0)),
                        'previous_average_result' => $this->roundMoney((float) $previousAverage),
                        'delta' => $this->roundMoney($delta),
                    ],
                    ['month_over_month'],
                    $this->fixesFromTopExpenses($context['top_expenses'], 2)
                );
            }
        }

        if ($openItemsCount >= (int) $this->threshold('many_open_items_count', 12.0)) {
            $severity = $openItemsCount >= (int) $this->threshold('many_open_items_high_count', 20.0)
                ? 'high'
                : 'medium';

            $topFiveOpenAmount = $this->roundMoney(
                (float) $context['outgoing_items']
                    ->sortByDesc('amount')
                    ->take(5)
                    ->sum('amount')
            );

            $findings[] = $this->makeFinding(
                'many_open_items',
                $severity,
                [
                    'open_items_count' => $openItemsCount,
                    'top_five_open_amount' => $topFiveOpenAmount,
                ],
                ['open_items.count_total'],
                [
                    $this->makeFix(
                        'Wenige grosse Posten zuerst',
                        'Schliesse zuerst die groessten offenen Positionen, um schnell Entlastung zu schaffen.',
                        $topFiveOpenAmount
                    ),
                ]
            );
        }

        usort($findings, function (array $left, array $right) {
            return $this->severityWeight($right['severity']) <=> $this->severityWeight($left['severity']);
        });

        return $findings;
    }

    private function buildFallbackResult(Month $month, array $context, array $findings): array
    {
        $prioritizedFindings = collect($findings)
            ->map(function (array $finding) {
                return [
                    'code' => $finding['code'],
                    'severity' => $finding['severity'],
                    'title' => $this->findingTitle($finding['code']),
                    'description' => $this->findingDescription($finding),
                ];
            })
            ->take(6)
            ->values()
            ->all();

        $suggestedFixes = $this->normalizeFixesFromFindings($findings, $context)->take(6)->values()->all();
        $questions = $this->buildQuestions($findings)->take(4)->values()->all();

        return [
            'summary' => $this->fallbackSummary($month, $context, $findings),
            'prioritized_findings' => $prioritizedFindings,
            'suggested_fixes' => $suggestedFixes,
            'questions' => $questions,
            'source' => 'fallback',
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function normalizeAiResult(array $aiData, Month $month, array $context, array $findings): ?array
    {
        $summary = is_string($aiData['summary'] ?? null) ? trim((string) $aiData['summary']) : '';
        $prioritizedFindings = $this->normalizePrioritizedFindings($aiData['prioritized_findings'] ?? null);
        $suggestedFixes = $this->normalizeSuggestedFixes($aiData['suggested_fixes'] ?? null);
        $questions = $this->normalizeQuestions($aiData['questions'] ?? null);

        if ($summary === '' && empty($prioritizedFindings) && empty($suggestedFixes) && empty($questions)) {
            Log::warning('AI response ignored because expected keys were empty.');

            return null;
        }

        if ($summary === '') {
            $summary = $this->fallbackSummary($month, $context, $findings);
        }

        if (empty($prioritizedFindings)) {
            $prioritizedFindings = collect($findings)
                ->map(fn (array $finding) => [
                    'code' => $finding['code'],
                    'severity' => $finding['severity'],
                    'title' => $this->findingTitle($finding['code']),
                    'description' => $this->findingDescription($finding),
                ])
                ->take(6)
                ->values()
                ->all();
        }

        if (empty($suggestedFixes)) {
            $suggestedFixes = $this->normalizeFixesFromFindings($findings, $context)
                ->take(6)
                ->values()
                ->all();
        }

        if (empty($questions)) {
            $questions = $this->buildQuestions($findings)->take(4)->values()->all();
        }

        return [
            'summary' => $summary,
            'prioritized_findings' => $prioritizedFindings,
            'suggested_fixes' => $suggestedFixes,
            'questions' => $questions,
            'source' => 'ai',
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function normalizePrioritizedFindings(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $title = trim($item);
                if ($title === '') {
                    continue;
                }

                $normalized[] = [
                    'code' => Str::slug($title, '_'),
                    'severity' => 'medium',
                    'title' => $title,
                    'description' => $title,
                ];

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $title = $this->firstString($item, ['title', 'label', 'code']);
            if ($title === '') {
                continue;
            }

            $description = $this->firstString($item, ['description', 'details', 'text']);
            if ($description === '') {
                $description = $title;
            }

            $normalized[] = [
                'code' => $this->firstString($item, ['code']) ?: Str::slug($title, '_'),
                'severity' => $this->normalizeSeverity($this->firstString($item, ['severity'])),
                'title' => $title,
                'description' => $description,
            ];
        }

        return array_values(array_slice($normalized, 0, 6));
    }

    private function normalizeSuggestedFixes(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $title = trim($item);
                if ($title === '') {
                    continue;
                }

                $normalized[] = [
                    'title' => $title,
                    'description' => $title,
                ];

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $title = $this->firstString($item, ['title', 'name', 'action']);
            if ($title === '') {
                continue;
            }

            $description = $this->firstString($item, ['description', 'details', 'reason']);
            if ($description === '') {
                $description = $title;
            }

            $normalizedItem = [
                'title' => $title,
                'description' => $description,
            ];

            $amount = $item['amount_reference'] ?? $item['amount_chf'] ?? $item['amount'] ?? null;
            if (is_numeric($amount)) {
                $normalizedItem['amount_reference'] = $this->roundMoney((float) $amount);
            }

            $normalized[] = $normalizedItem;
        }

        return array_values(array_slice($normalized, 0, 8));
    }

    private function normalizeQuestions(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $questions = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $question = trim($item);
                if ($question !== '') {
                    $questions[] = $question;
                }

                continue;
            }

            if (is_array($item)) {
                $question = $this->firstString($item, ['question', 'text', 'title']);
                if ($question !== '') {
                    $questions[] = $question;
                }
            }
        }

        return array_values(array_slice(array_unique($questions), 0, 5));
    }

    private function normalizeFixesFromFindings(array $findings, array $context): Collection
    {
        $fixes = collect();
        foreach ($findings as $finding) {
            foreach ($finding['suggested_fixes'] ?? [] as $fix) {
                if (! is_array($fix)) {
                    continue;
                }

                $title = trim((string) ($fix['title'] ?? ''));
                $description = trim((string) ($fix['description'] ?? ''));
                if ($title === '' || $description === '') {
                    continue;
                }

                $item = [
                    'title' => $title,
                    'description' => $description,
                ];

                if (isset($fix['amount_reference']) && is_numeric($fix['amount_reference'])) {
                    $item['amount_reference'] = $this->roundMoney((float) $fix['amount_reference']);
                }

                $fixes->push($item);
            }
        }

        if ($fixes->isEmpty()) {
            $firstExpense = $context['top_expenses']->first();
            if (is_array($firstExpense)) {
                $amount = (float) ($firstExpense['amount'] ?? 0);
                $fixes->push([
                    'title' => 'Groessten Kostenblock zuerst angehen',
                    'description' => sprintf(
                        'Starte bei "%s". Schon kleine Anpassungen wirken dort am staerksten.',
                        (string) ($firstExpense['label'] ?? 'dem groessten Posten')
                    ),
                    'amount_reference' => $this->roundMoney($amount * 0.1),
                ]);
            }
        }

        return $fixes
            ->unique(fn (array $fix) => Str::lower($fix['title'].'|'.$fix['description']))
            ->values();
    }

    private function buildQuestions(array $findings): Collection
    {
        $codes = collect($findings)->pluck('code')->all();
        $questions = collect();

        if (in_array('expected_income_dependency_high', $codes, true)) {
            $questions->push('Welche erwarteten Einnahmen sind bis Monatsende wirklich sicher?');
        }

        if (in_array('overdue_expenses_present', $codes, true)) {
            $questions->push('Welche ueberfaelligen Posten willst du heute als Erstes erledigen?');
        }

        if (in_array('many_open_items', $codes, true)) {
            $questions->push('Welche offenen Posten kannst du zusammenfassen oder verschieben?');
        }

        $questions->push('Welche Ausgabe koenntest du in diesem Monat am einfachsten senken?');
        $questions->push('Gibt es eine Einnahme, die du frueher absichern kannst?');
        $questions->push('Soll ein fester Puffer fuer den naechsten Monat reserviert werden?');

        return $questions->unique()->values();
    }

    private function fallbackSummary(Month $month, array $context, array $findings): string
    {
        $metrics = $context['metrics'];
        $incomeTotal = $this->roundMoney((float) ($metrics['income_total'] ?? 0));
        $outgoingTotal = $this->roundMoney((float) ($context['outgoing_open_total'] ?? 0));
        $result = $this->roundMoney((float) ($metrics['result'] ?? 0));
        $overdueCount = (int) $context['overdue_outgoing_items']->count();
        $overdueAmount = $this->roundMoney((float) $context['overdue_outgoing_items']->sum('amount'));
        $expectedShare = $incomeTotal > 0
            ? round(((float) ($context['expected_income_open_total'] ?? 0) / $incomeTotal) * 100, 1)
            : 0.0;

        $summary = sprintf(
            'Im Monat %s stehen aktuell offene Kosten von CHF %.2f offenen Einnahmen von CHF %.2f gegenueber. Das ergibt ein Monatsergebnis von CHF %.2f.',
            $month->name,
            $outgoingTotal,
            $incomeTotal,
            $result
        );

        if ($overdueCount > 0) {
            $summary .= sprintf(
                ' Es gibt %d ueberfaellige Posten mit zusammen CHF %.2f.',
                $overdueCount,
                $overdueAmount
            );
        }

        if ($expectedShare > 0) {
            $summary .= sprintf(
                ' Rund %.1f%% der offenen Einnahmen sind noch erwartete Einnahmen.',
                $expectedShare
            );
        }

        if (! empty($findings)) {
            $top = $findings[0];
            $summary .= sprintf(
                ' Prioritaet: %s.',
                $this->findingTitle($top['code'])
            );
        }

        return $summary;
    }

    private function findingTitle(string $code): string
    {
        return match ($code) {
            'result_negative' => 'Monatsergebnis negativ',
            'expense_pressure_high' => 'Hoher Ausgabendruck',
            'expected_income_dependency_high' => 'Starke Abhaengigkeit von erwarteten Einnahmen',
            'overdue_expenses_present' => 'Ueberfaellige Ausgaben vorhanden',
            'single_expense_concentration' => 'Einzelposten ist sehr dominant',
            'low_buffer_vs_open_items' => 'Puffer im Verhaeltnis zu offenen Kosten niedrig',
            'month_over_month_deterioration' => 'Monat entwickelt sich schlechter als zuvor',
            'many_open_items' => 'Viele offene Posten',
            default => 'Auffaelligkeit',
        };
    }

    private function findingDescription(array $finding): string
    {
        $details = $finding['details'] ?? [];

        return match ($finding['code']) {
            'result_negative' => sprintf(
                'Aktuell fehlen CHF %.2f bis zum Ausgleich.',
                (float) ($details['gap_to_zero'] ?? 0)
            ),
            'expense_pressure_high' => sprintf(
                'Offene Kosten liegen bei %.1f%% der offenen Einnahmen.',
                (float) ($details['expense_pressure_percent'] ?? 0)
            ),
            'expected_income_dependency_high' => sprintf(
                'Etwa %.1f%% deiner offenen Einnahmen sind noch nicht gesichert.',
                (float) ($details['dependency_percent'] ?? 0)
            ),
            'overdue_expenses_present' => sprintf(
                '%d Posten sind ueberfaellig (CHF %.2f).',
                (int) ($details['overdue_count'] ?? 0),
                (float) ($details['overdue_amount'] ?? 0)
            ),
            'single_expense_concentration' => sprintf(
                '"%s" macht %.1f%% deiner offenen Kosten aus.',
                (string) ($details['expense_label'] ?? 'Ein Posten'),
                (float) ($details['share_percent'] ?? 0)
            ),
            'low_buffer_vs_open_items' => sprintf(
                'Der aktuelle Puffer deckt nur %.1f%% der offenen Kosten.',
                (float) ($details['buffer_ratio_percent'] ?? 0)
            ),
            'month_over_month_deterioration' => sprintf(
                'Gegenueber dem Schnitt der Vormonate liegt das Ergebnis um CHF %.2f tiefer.',
                abs((float) ($details['delta'] ?? 0))
            ),
            'many_open_items' => sprintf(
                '%d offene Posten machen die Steuerung unuebersichtlich.',
                (int) ($details['open_items_count'] ?? 0)
            ),
            default => 'Es gibt eine Auffaelligkeit mit Handlungsbedarf.',
        };
    }

    private function makeFinding(
        string $code,
        string $severity,
        array $details,
        array $fieldPaths = [],
        array $suggestedFixes = []
    ): array {
        $finding = [
            'code' => $code,
            'severity' => $this->normalizeSeverity($severity),
            'details' => $details,
        ];

        if (! empty($fieldPaths)) {
            $finding['field_paths'] = array_values($fieldPaths);
        }

        if (! empty($suggestedFixes)) {
            $finding['suggested_fixes'] = array_values($suggestedFixes);
        }

        return $finding;
    }

    private function makeFix(string $title, string $description, ?float $amount = null, ?float $sharePercent = null): array
    {
        $fix = [
            'title' => $title,
            'description' => $description,
        ];

        if ($amount !== null) {
            $fix['amount_reference'] = $this->roundMoney($amount);
        }

        if ($sharePercent !== null) {
            $fix['share_percent'] = round($sharePercent, 1);
        }

        return $fix;
    }

    private function fixesFromTopExpenses(Collection $topExpenses, int $limit = 2): array
    {
        return $topExpenses
            ->take($limit)
            ->map(function (array $expense) {
                $amount = (float) ($expense['amount'] ?? 0);
                $sharePercent = (float) ($expense['share_of_open_costs_percent'] ?? 0);

                return $this->makeFix(
                    sprintf('Kostenblock "%s" zuerst pruefen', (string) ($expense['label'] ?? 'Top-Posten')),
                    sprintf(
                        'Wenn du dort 10%% reduzierst, werden rund CHF %.2f frei.',
                        $amount * 0.1
                    ),
                    $amount * 0.1,
                    $sharePercent
                );
            })
            ->values()
            ->all();
    }

    private function topExpenses(Collection $outgoingItems, float $livingCostOpen, float $outgoingOpenTotal): Collection
    {
        $groups = $outgoingItems->groupBy(function (array $item) {
            $label = trim((string) ($item['description'] ?? ''));
            if ($label === '') {
                $label = 'Ohne Bezeichnung';
            }

            return Str::lower($label);
        });

        $rows = $groups->map(function (Collection $group) use ($outgoingOpenTotal) {
            $amount = (float) $group->sum('amount');
            $label = trim((string) ($group->first()['description'] ?? ''));
            if ($label === '') {
                $label = 'Ohne Bezeichnung';
            }

            $ratio = $outgoingOpenTotal > 0 ? $amount / $outgoingOpenTotal : 0.0;

            return [
                'label' => $label,
                'amount' => $this->roundMoney($amount),
                'share_of_open_costs' => round($ratio, 4),
                'share_of_open_costs_percent' => round($ratio * 100, 1),
                'item_count' => $group->count(),
            ];
        })->values();

        if ($livingCostOpen > 0) {
            $ratio = $outgoingOpenTotal > 0 ? $livingCostOpen / $outgoingOpenTotal : 0.0;
            $rows->push([
                'label' => 'Lebensunterhalt',
                'amount' => $this->roundMoney($livingCostOpen),
                'share_of_open_costs' => round($ratio, 4),
                'share_of_open_costs_percent' => round($ratio * 100, 1),
                'item_count' => 1,
            ]);
        }

        return $rows
            ->sortByDesc('amount')
            ->take(5)
            ->values();
    }

    private function upcomingDueBuckets(Collection $outgoingItems, Carbon $today): array
    {
        $buckets = [];
        foreach ([7, 14, 30] as $days) {
            $end = $today->copy()->addDays($days);
            $items = $outgoingItems->filter(function (array $item) use ($today, $end) {
                $dueDate = Carbon::parse($item['due_date'])->startOfDay();

                return $dueDate->between($today, $end, true);
            });

            $buckets[] = [
                'days' => $days,
                'count' => $items->count(),
                'amount' => $this->roundMoney((float) $items->sum('amount')),
            ];
        }

        return $buckets;
    }

    private function historyResults(Month $month, User $user): Collection
    {
        $months = Month::forUser($user)
            ->visible()
            ->whereDate('date_from', '<', $month->date_from->toDateString())
            ->orderByDesc('date_from')
            ->limit($this->historyWindow())
            ->get();

        return $months->map(function (Month $historyMonth) {
            $variants = $this->metricsService->calculateVariants($historyMonth);
            $metrics = $historyMonth->is_current ? $variants['with_balance'] : $variants['without_balance'];

            return [
                'month_id' => $historyMonth->id,
                'month_name' => (string) $historyMonth->name,
                'result' => $this->roundMoney((float) ($metrics['result'] ?? 0)),
                'income_total' => $this->roundMoney((float) ($metrics['income_total'] ?? 0)),
                'open_expenses' => $this->roundMoney((float) ($metrics['open_expenses'] ?? 0)),
                'living_cost_open' => $this->roundMoney((float) ($metrics['living_cost_open'] ?? 0)),
            ];
        })->values();
    }

    private function resolveIncomeSource(Entry $entry): string
    {
        if ($entry->income_source !== null) {
            return (string) $entry->income_source;
        }

        if ($entry->recurring_template_id) {
            return 'manual';
        }

        return in_array($entry->account?->type, ['forecast', 'clearing'], true)
            ? 'expected'
            : 'manual';
    }

    private function resolveDueDate(Entry $entry, Month $month): Carbon
    {
        if ($entry->due_date) {
            return $entry->due_date->copy()->startOfDay();
        }

        if ($entry->entry_date) {
            return $entry->entry_date->copy()->startOfDay();
        }

        return $month->date_to->copy()->startOfDay();
    }

    private function normalizeSeverity(string $severity): string
    {
        return match (Str::lower(trim($severity))) {
            'high' => 'high',
            'low' => 'low',
            default => 'medium',
        };
    }

    private function severityWeight(string $severity): int
    {
        return match ($this->normalizeSeverity($severity)) {
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }

    private function threshold(string $key, float $default): float
    {
        $value = config('services.ai.thresholds.'.$key, $default);
        if (! is_numeric($value)) {
            return $default;
        }

        return (float) $value;
    }

    private function historyWindow(): int
    {
        return max(3, (int) $this->threshold('history_months', 6.0));
    }

    private function cacheKey(Month $month, User $user): string
    {
        return sprintf(
            'budget-insights:v1:user:%d:month:%d:%s',
            $user->id,
            $month->id,
            $this->dataFingerprint($month, $user)
        );
    }

    private function dataFingerprint(Month $month, User $user): string
    {
        $monthIds = Month::forUser($user)
            ->visible()
            ->whereDate('date_from', '<=', $month->date_from->toDateString())
            ->orderByDesc('date_from')
            ->limit($this->historyWindow() + 1)
            ->pluck('id');

        if ($monthIds->isEmpty()) {
            $monthIds = collect([$month->id]);
        }

        $monthsUpdatedAt = Month::forUser($user)
            ->whereIn('id', $monthIds)
            ->max('updated_at');
        $entriesUpdatedAt = Entry::query()
            ->where('user_id', $user->id)
            ->whereIn('month_id', $monthIds)
            ->max('updated_at');
        $entryCount = Entry::query()
            ->where('user_id', $user->id)
            ->whereIn('month_id', $monthIds)
            ->count();
        $entryAmountSum = Entry::query()
            ->where('user_id', $user->id)
            ->whereIn('month_id', $monthIds)
            ->sum('amount');

        $raw = implode('|', [
            $user->id,
            $month->id,
            $monthIds->implode(','),
            $this->timestampValue($monthsUpdatedAt),
            $this->timestampValue($entriesUpdatedAt),
            $entryCount,
            $this->roundMoney((float) $entryAmountSum),
        ]);

        return sha1($raw);
    }

    private function timestampValue(mixed $value): int
    {
        if ($value instanceof Carbon) {
            return $value->getTimestamp();
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->getTimestamp();
        }

        return 0;
    }

    private function tenantId(User $user): string
    {
        $prefix = trim((string) config('services.ai.tenant_prefix', 'budget-user'));
        if ($prefix === '') {
            $prefix = 'budget-user';
        }

        return $prefix.'-'.$user->id;
    }

    private function firstString(array $item, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $item)) {
                continue;
            }

            if (is_string($item[$key])) {
                $value = trim($item[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    private function roundMoney(float $value): float
    {
        return round($value, 2);
    }

    private function assertUserScope(Month $month, User $user): void
    {
        if ($month->user_id !== $user->id) {
            abort(403);
        }
    }
}
