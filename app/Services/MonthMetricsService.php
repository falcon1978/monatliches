<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\Month;
use App\Services\AccountBalanceService;
use Illuminate\Support\Carbon;

class MonthMetricsService
{
    public function __construct(private AccountBalanceService $balanceService)
    {
    }

    public function calculate(Month $month): array
    {
        $openForecastIncome = $this->openForecastIncome($month);
        $nonCustomerIncome = $this->nonCustomerIncome($month);
        $balanceIncome = $this->balanceIncome($month);
        $openExpenses = $this->openExpenses($month);
        $remainingDays = $month->remainingDaysForLivingCost();
        $livingCostOpen = round($remainingDays * (float) $month->daily_living_cost, 2);
        $incomeTotal = round($openForecastIncome + $nonCustomerIncome + $balanceIncome, 2);
        $result = round($incomeTotal - ($openExpenses + $livingCostOpen), 2);
        $workdaysRemaining = $month->workdaysRemaining();
        $requiredRevenuePerWorkday = $workdaysRemaining > 0
            ? round($result / $workdaysRemaining, 2)
            : 0.0;

        return [
            'open_forecast_income' => $openForecastIncome,
            'non_customer_income' => $nonCustomerIncome,
            'balance_income' => $balanceIncome,
            'income_total' => $incomeTotal,
            'open_expenses' => $openExpenses,
            'living_cost_open' => $livingCostOpen,
            'result' => $result,
            'workdays_remaining' => $workdaysRemaining,
            'required_revenue_per_workday' => $requiredRevenuePerWorkday,
            'remaining_days' => $remainingDays,
        ];
    }

    public function requiredRevenuePerWorkdayFromToday(Month $targetMonth): float
    {
        return $this->cumulativeFromToday($targetMonth)['required_per_workday'];
    }

    public function cumulativeFromToday(Month $targetMonth): array
    {
        $today = now()->startOfDay();

        if ($targetMonth->date_to->lt($today)) {
            return [
                'result_sum' => 0.0,
                'workdays_sum' => 0,
                'required_per_workday' => 0.0,
            ];
        }

        $months = Month::forUser($targetMonth->user)
            ->whereDate('date_to', '>=', $today->toDateString())
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

        $sumResults = 0.0;
        foreach ($months as $month) {
            $sumResults += $this->calculate($month)['result'];
        }

        $workdays = $this->countWorkdaysBetween($today, $targetMonth->date_to->copy()->startOfDay());

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

    private function countWorkdaysBetween(Carbon $start, Carbon $end): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        $cursor = $start->copy();
        $workdays = 0;

        while ($cursor->lte($end)) {
            if ($cursor->isWeekday()) {
                $workdays++;
            }
            $cursor->addDay();
        }

        return $workdays;
    }
}
