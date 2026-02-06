<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    public const LIVING_COST_MODES = ['deduct', 'keep', 'custom'];

    protected $fillable = [
        'user_id',
        'name',
        'date_from',
        'date_to',
        'living_cost_mode',
        'custom_living_cost',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'custom_living_cost' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOverlapping(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query
            ->whereDate('date_to', '>=', $start->toDateString())
            ->whereDate('date_from', '<=', $end->toDateString());
    }

    public static function dateSetForUser(User $user, Carbon $start, Carbon $end, ?array $modes = null): array
    {
        if ($end->lt($start)) {
            return [];
        }

        if (is_array($modes) && count($modes) === 0) {
            return [];
        }

        $query = static::forUser($user)
            ->overlapping($start, $end);

        if ($modes) {
            $query->whereIn('living_cost_mode', $modes);
        }

        $holidays = $query->get(['date_from', 'date_to']);
        if ($holidays->isEmpty()) {
            return [];
        }

        $dates = [];
        foreach ($holidays as $holiday) {
            $rangeStart = $holiday->date_from->copy()->startOfDay();
            $rangeEnd = $holiday->date_to->copy()->startOfDay();

            if ($rangeStart->lt($start)) {
                $rangeStart = $start->copy();
            }
            if ($rangeEnd->gt($end)) {
                $rangeEnd = $end->copy();
            }

            $cursor = $rangeStart->copy();
            while ($cursor->lte($rangeEnd)) {
                $dates[$cursor->toDateString()] = true;
                $cursor->addDay();
            }
        }

        return $dates;
    }

    public static function customAmountForUser(User $user, Carbon $start, Carbon $end): float
    {
        if ($end->lt($start)) {
            return 0.0;
        }

        $holidays = static::forUser($user)
            ->overlapping($start, $end)
            ->where('living_cost_mode', 'custom')
            ->whereNotNull('custom_living_cost')
            ->get(['date_from', 'date_to', 'custom_living_cost']);

        if ($holidays->isEmpty()) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($holidays as $holiday) {
            $holidayStart = $holiday->date_from->copy()->startOfDay();
            $holidayEnd = $holiday->date_to->copy()->startOfDay();

            $overlapStart = $holidayStart->lt($start) ? $start->copy() : $holidayStart;
            $overlapEnd = $holidayEnd->gt($end) ? $end->copy() : $holidayEnd;
            if ($overlapEnd->lt($overlapStart)) {
                continue;
            }

            $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;
            $sum += (float) $holiday->custom_living_cost * $overlapDays;
        }

        return round($sum, 2);
    }
}
