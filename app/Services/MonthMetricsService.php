<?php

namespace App\Services;

use App\Models\AccountBalance;
use App\Models\Entry;
use App\Models\Holiday;
use App\Models\Month;
use App\Models\User;
use App\Services\AccountBalanceService;
use Illuminate\Support\Carbon;

class MonthMetricsService
{
    public function __construct(private AccountBalanceService $balanceService)
    {
    }

    public function calculate(Month $month, ?Carbon $today = null, bool $includeBalance = true): array
    {
        $base = $this->baseMetrics($month, $today);

        return $this->buildMetrics($base, $includeBalance);
    }

    public function calculateVariants(Month $month, ?Carbon $today = null): array
    {
        $base = $this->baseMetrics($month, $today);

        return [
            'without_balance' => $this->buildMetrics($base, false),
            'with_balance' => $this->buildMetrics($base, true),
        ];
    }

    public function requiredRevenuePerWorkdayFromToday(Month $targetMonth): float
    {
        return $this->cumulativeFromToday($targetMonth)['required_per_workday'];
    }

    public function cumulativeFromToday(Month $targetMonth): array
    {
        $today = now()->startOfDay();
        $currentMonth = Month::forUser($targetMonth->user)
            ->visible()
            ->where('is_current', true)
            ->first();

        if ($targetMonth->date_to->lt($today) && (! $currentMonth || $currentMonth->date_from->gt($targetMonth->date_to))) {
            return [
                'result_sum' => 0.0,
                'workdays_sum' => 0,
                'required_per_workday' => 0.0,
            ];
        }

        $sumStart = $currentMonth ? $currentMonth->date_from->copy()->startOfDay() : $today;

        $months = Month::forUser($targetMonth->user)
            ->visible()
            ->whereDate('date_to', '>=', $sumStart->toDateString())
            ->whereDate('date_from', '<=', $targetMonth->date_to->toDateString())
            ->orderBy('date_from')
            ->get();

        if ($months->isEmpty()) {
            return [
                'result_sum' => 0.0,
                'workdays_sum' => 0,
                'required_per_workday' => 0.0,
            ];
        }

        $carryOver = 0.0;
        $hasBaseBalance = AccountBalance::forUser($targetMonth->user)->exists();

        if (! $hasBaseBalance) {
            $firstMonth = $months->first();
            $carryOverMonths = Month::forUser($targetMonth->user)
                ->visible()
                ->whereDate('date_to', '<', $firstMonth->date_from->toDateString())
                ->orderBy('date_from')
                ->get();

            foreach ($carryOverMonths as $month) {
                $carryOver += $this->calculate($month, $month->date_from->copy(), false)['result'];
            }
        }

        $sumResults = $carryOver;
        foreach ($months as $month) {
            $sumResults += $this->calculate($month, null, $month->is_current)['result'];
        }

        $applyHolidays = $targetMonth->user?->isSelfEmployed() ?? false;
        $workdays = $this->countWorkdaysBetween(
            $today,
            $targetMonth->date_to->copy()->startOfDay(),
            $applyHolidays ? $targetMonth->user : null
        );

        return [
            'result_sum' => round($sumResults, 2),
            'workdays_sum' => $workdays,
            'required_per_workday' => $workdays > 0 ? round($sumResults / $workdays, 2) : 0.0,
        ];
    }

    public function openForecastIncome(Month $month): float
    {
        $incomes = Entry::query()
            ->where('month_id', $month->id)
            ->where('type', 'income')
            ->where('direction', 'in')
            ->whereIn('status', ['open', 'partial'])
            ->whereNull('recurring_template_id')
            ->where(function ($query) {
                $query->where('income_source', 'expected')
                    ->orWhere(function ($query) {
                        $query->whereNull('income_source')
                            ->whereHas('account', static function ($query) {
                                $query->where('type', 'forecast');
                            });
                    });
            })
            ->whereHas('account', static function ($query) {
                $query->where('type', 'forecast');
            })
            ->with('relatedTransfersOut')
            ->get();

        return round($incomes->sum(static fn (Entry $entry) => $entry->open_amount), 2);
    }

    public function nonCustomerIncome(Month $month): float
    {
        $incomes = Entry::query()
            ->where('month_id', $month->id)
            ->where('type', 'income')
            ->where('direction', 'in')
            ->whereIn('status', ['open', 'partial'])
            ->where(function ($query) {
                $query->where('income_source', 'manual')
                    ->orWhereNotNull('recurring_template_id')
                    ->orWhere(function ($query) {
                        $query->whereNull('income_source')
                            ->whereNull('recurring_template_id')
                            ->whereHas('account', static function ($query) {
                                $query->where('type', '!=', 'forecast');
                            });
                    });
            })
            ->with('relatedTransfersOut')
            ->get();

        return round($incomes->sum(static fn (Entry $entry) => $entry->open_amount), 2);
    }

    public function openExpenses(Month $month): float
    {
        return round((float) Entry::query()
            ->where('month_id', $month->id)
            ->whereIn('type', ['expense', 'fixcost'])
            ->where('direction', 'out')
            ->where('status', 'open')
            ->sum('amount'), 2);
    }

    public function balanceIncome(Month $month): float
    {
        return $this->balanceService->effectiveBalanceSum($month);
    }

    private function baseMetrics(Month $month, ?Carbon $today = null): array
    {
        $today = $today?->copy() ?? now()->startOfDay();
        $openForecastIncome = $this->openForecastIncome($month);
        $nonCustomerIncome = $this->nonCustomerIncome($month);
        $balanceIncome = $this->balanceIncome($month);
        $openExpenses = $this->openExpenses($month);
        $livingSummary = $month->livingCostSummary($today);
        $remainingDays = (int) ($livingSummary['remaining_days'] ?? 0);
        $rawLivingCostBase = round((float) ($livingSummary['base'] ?? 0), 2);
        $rawHolidayCustomLivingCost = round((float) ($livingSummary['custom_total'] ?? 0), 2);
        $rawHolidayDeductedDays = (int) ($livingSummary['deducted_days'] ?? 0);
        $nextMonthLivingCost = 0.0;
        $nextMonthLivingCostBase = 0.0;
        $nextMonthHolidayCustomLivingCost = 0.0;
        $user = $month->user;
        $isCurrentRange = $today->between($month->date_from, $month->date_to, true);
        $includeCurrentLivingCost = $isCurrentRange;
        $includeNextMonthLivingCost = (bool) $user;
        if ($includeNextMonthLivingCost) {
            $nextStart = $month->date_from->copy()->addMonthNoOverflow()->startOfMonth();
            $nextMonth = Month::forUser($user)
                ->visible()
                ->whereDate('date_from', $nextStart->toDateString())
                ->first();
            if ($nextMonth) {
                $nextSummary = $nextMonth->livingCostSummary($nextMonth->date_from->copy()->startOfDay());
                $nextMonthLivingCostBase = round((float) ($nextSummary['base'] ?? 0), 2);
                $nextMonthHolidayCustomLivingCost = round((float) ($nextSummary['custom_total'] ?? 0), 2);
                $nextMonthLivingCost = round((float) ($nextSummary['total'] ?? 0), 2);
            }
        }
        $livingCostBase = $includeCurrentLivingCost ? $rawLivingCostBase : 0.0;
        $holidayCustomLivingCost = $includeCurrentLivingCost ? $rawHolidayCustomLivingCost : 0.0;
        $holidayDeductedDays = $includeCurrentLivingCost ? $rawHolidayDeductedDays : 0;
        $livingCostOpen = round(
            ($includeCurrentLivingCost ? (float) ($livingSummary['total'] ?? 0) : 0.0) + $nextMonthLivingCost,
            2
        );
        $workdaysRemaining = $month->workdaysRemaining($today);
        $holidayWorkdaysDeducted = $month->holidayWorkdaysDeducted();

        return [
            'open_forecast_income' => $openForecastIncome,
            'non_customer_income' => $nonCustomerIncome,
            'balance_income' => $balanceIncome,
            'open_expenses' => $openExpenses,
            'living_cost_open' => $livingCostOpen,
            'living_cost_base' => $livingCostBase,
            'holiday_custom_living_cost' => $holidayCustomLivingCost,
            'holiday_deducted_days' => $holidayDeductedDays,
            'next_month_living_cost' => $nextMonthLivingCost,
            'next_month_living_cost_base' => $nextMonthLivingCostBase,
            'next_month_holiday_custom_living_cost' => $nextMonthHolidayCustomLivingCost,
            'include_current_living_cost' => $includeCurrentLivingCost,
            'holiday_workdays_deducted' => $holidayWorkdaysDeducted,
            'workdays_remaining' => $workdaysRemaining,
            'remaining_days' => $remainingDays,
        ];
    }

    private function buildMetrics(array $base, bool $includeBalance): array
    {
        $balanceContribution = $includeBalance ? (float) $base['balance_income'] : 0.0;
        $incomeTotal = round(
            (float) $base['open_forecast_income'] + (float) $base['non_customer_income'] + $balanceContribution,
            2
        );
        $result = round(
            $incomeTotal - ((float) $base['open_expenses'] + (float) $base['living_cost_open']),
            2
        );
        $workdaysRemaining = (int) $base['workdays_remaining'];
        $requiredRevenuePerWorkday = $workdaysRemaining > 0
            ? round($result / $workdaysRemaining, 2)
            : 0.0;

        return array_merge($base, [
            'income_total' => $incomeTotal,
            'result' => $result,
            'required_revenue_per_workday' => $requiredRevenuePerWorkday,
        ]);
    }

    private function countWorkdaysBetween(Carbon $start, Carbon $end, ?User $user = null): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        $cursor = $start->copy();
        $workdays = 0;
        $holidayDates = [];
        if ($user) {
            $holidayDates = Holiday::dateSetForUser($user, $start, $end);
        }

        while ($cursor->lte($end)) {
            if ($cursor->isWeekday() && ! isset($holidayDates[$cursor->toDateString()])) {
                $workdays++;
            }
            $cursor->addDay();
        }

        return $workdays;
    }
}
