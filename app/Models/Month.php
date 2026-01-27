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
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'daily_living_cost' => 'decimal:2',
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

    public function remainingDaysForLivingCost(?Carbon $today = null): int
    {
        $today = $today?->copy() ?? now()->startOfDay();
        $start = $this->date_from->copy();
        $end = $this->date_to->copy();

        if ($today->gt($end)) {
            return 0;
        }

        $rangeStart = $today->between($start, $end, true) ? $today : $start;

        return $rangeStart->diffInDays($end) + 1;
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

        while ($cursor->lte($end)) {
            if (! $cursor->isWeekend()) {
                $days++;
            }
            $cursor->addDay();
        }

        return $days;
    }
}
