<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    protected $fillable = [
        'user_id',
        'month_id',
        'entry_date',
        'due_date',
        'type',
        'income_source',
        'direction',
        'amount',
        'account_id',
        'status',
        'description',
        'related_entry_id',
        'transfer_group_id',
        'recurring_template_id',
        'moved_from_month_id',
        'origin_month_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function month()
    {
        return $this->belongsTo(Month::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function relatedEntry()
    {
        return $this->belongsTo(Entry::class, 'related_entry_id');
    }

    public function relatedTransfersOut()
    {
        return $this->hasMany(Entry::class, 'related_entry_id')
            ->where('type', 'transfer')
            ->where('direction', 'out');
    }

    public function recurringTemplate()
    {
        return $this->belongsTo(RecurringTemplate::class);
    }

    public function movedFromMonth()
    {
        return $this->belongsTo(Month::class, 'moved_from_month_id');
    }

    public function originMonth()
    {
        return $this->belongsTo(Month::class, 'origin_month_id');
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function getOpenAmountAttribute(): float
    {
        if ($this->type !== 'income') {
            return (float) $this->amount;
        }

        $source = $this->income_source;
        if ($source === null) {
            $source = $this->recurring_template_id
                ? 'manual'
                : (in_array($this->account?->type, ['forecast', 'clearing'], true) ? 'expected' : 'manual');
        }

        $hasTransfers = $this->relationLoaded('relatedTransfersOut')
            ? $this->relatedTransfersOut->isNotEmpty()
            : $this->relatedTransfersOut()->exists();

        if ($source === 'expected' || $hasTransfers) {
            $paid = $this->relationLoaded('relatedTransfersOut')
                ? $this->relatedTransfersOut->sum('amount')
                : $this->relatedTransfersOut()->sum('amount');

            $remaining = round((float) $this->amount - (float) $paid, 2);
            if ((float) $this->amount < 0) {
                return $remaining;
            }
            return max(0, $remaining);
        }

        return $this->status === 'paid' ? 0.0 : (float) $this->amount;
    }
}
