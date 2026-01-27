<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringTemplate extends Model
{
    protected $fillable = [
        'user_id',
        'kind',
        'name',
        'amount',
        'remaining_amount',
        'ends_on',
        'currency',
        'frequency',
        'months_mask',
        'default_account_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'ends_on' => 'date',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function defaultAccount()
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function appliesToMonth(Month $month): bool
    {
        if ($this->ends_on && $month->date_from->gt($this->ends_on)) {
            return false;
        }

        $monthNumber = $month->date_from->month;
        $monthsMask = $this->parsedMonthsMask();

        return match ($this->frequency) {
            'monthly' => true,
            'quarterly' => in_array($monthNumber, $monthsMask ?: [1, 4, 7, 10], true),
            'yearly' => in_array($monthNumber, $monthsMask ?: [$this->created_at?->month ?? 1], true),
            'custom_months' => in_array($monthNumber, $monthsMask, true),
            default => false,
        };
    }

    public function parsedMonthsMask(): array
    {
        if (! $this->months_mask) {
            return [];
        }

        $raw = trim($this->months_mask);
        if ($raw === '') {
            return [];
        }

        $months = [];
        if (str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $months = $decoded;
            }
        } else {
            $months = explode(',', $raw);
        }

        return array_values(array_filter(array_map(
            static fn ($value) => (int) trim((string) $value),
            $months
        ), static fn ($month) => $month >= 1 && $month <= 12));
    }
}
