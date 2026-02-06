<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Month extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'date_from',
        'date_to',
        'daily_living_cost',
        'is_current',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'daily_living_cost' => 'decimal:2',
            'is_current' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeVisible($query)
    {
        return $query->whereNull('archived_at');
    }

    public function remainingDaysForLivingCost(?Carbon $today = null): int
    {
        return $this->livingCostSummary($today)['remaining_days'];
    }

    public function workdaysRemaining(?Carbon $today = null): int
    {
        $today = $today?->copy() ?? now()->startOfDay();
        $start = $this->date_from->copy();
        $end = $this->date_to->copy();

        if ($today->gt($end)) {
            return 0;
        }

        $rangeStart = $today->between($start, $end, true) ? $today : $start;
        $days = 0;
        $cursor = $rangeStart->copy();
        $holidayDates = [];
        $user = $this->relationLoaded('user') ? $this->user : $this->user()->first();
        if ($user?->isSelfEmployed()) {
            $holidayDates = $this->holidayDateSet($rangeStart, $end, null, $user);
        }

        while ($cursor->lte($end)) {
            if (! $cursor->isWeekend() && ! isset($holidayDates[$cursor->toDateString()])) {
                $days++;
            }
            $cursor->addDay();
        }

        return $days;
    }

    public function holidayWorkdaysDeducted(): int
    {
        $user = $this->relationLoaded('user') ? $this->user : $this->user()->first();
        if (! $user?->isSelfEmployed()) {
            return 0;
        }

        $start = $this->date_from->copy();
        $end = $this->date_to->copy();
        if ($start->gt($end)) {
            return 0;
        }

        $holidayDates = $this->holidayDateSet($start, $end, null, $user);
        if (empty($holidayDates)) {
            return 0;
        }

        $count = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if ($cursor->isWeekday() && isset($holidayDates[$cursor->toDateString()])) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    public function livingCostSummary(?Carbon $today = null): array
    {
        $today = $today?->copy() ?? now()->startOfDay();
        $start = $this->date_from->copy();
        $end = $this->date_to->copy();

        if ($today->gt($end)) {
            return [
                'remaining_days' => 0,
                'deducted_days' => 0,
                'base' => 0.0,
                'custom_total' => 0.0,
                'total' => 0.0,
            ];
        }

        $rangeStart = $today->between($start, $end, true) ? $today : $start;
        $totalDays = $rangeStart->diffInDays($end) + 1;
        $user = $this->relationLoaded('user') ? $this->user : $this->user()->first();
        if (! $user) {
            $base = $totalDays * (float) $this->daily_living_cost;

            return [
                'remaining_days' => $totalDays,
                'deducted_days' => 0,
                'base' => $base,
                'custom_total' => 0.0,
                'total' => $base,
            ];
        }

        $holidayDates = $this->holidayDateSet($rangeStart, $end, ['deduct', 'custom'], $user);
        $deductedDays = count($holidayDates);
        $remainingDays = max(0, $totalDays - $deductedDays);
        $base = $remainingDays * (float) $this->daily_living_cost;
        $customTotal = Holiday::customAmountForUser($user, $rangeStart, $end);

        return [
            'remaining_days' => $remainingDays,
            'deducted_days' => $deductedDays,
            'base' => $base,
            'custom_total' => $customTotal,
            'total' => $base + $customTotal,
        ];
    }

    private function holidayDateSet(Carbon $start, Carbon $end, ?array $modes = null, ?User $user = null): array
    {
        if ($end->lt($start)) {
            return [];
        }

        $user = $user ?? ($this->relationLoaded('user') ? $this->user : $this->user()->first());
        if (! $user) {
            return [];
        }

        return Holiday::dateSetForUser($user, $start, $end, $modes);
    }
}
