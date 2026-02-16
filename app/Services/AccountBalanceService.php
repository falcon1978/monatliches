<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\Entry;
use App\Models\Month;
use Illuminate\Support\Collection;

class AccountBalanceService
{
    public function movementsForMonth(Month $month, ?Collection $accounts = null): array
    {
        $query = Entry::query()
            ->where('user_id', $month->user_id)
            ->whereIn('type', ['income', 'expense', 'fixcost', 'transfer'])
            ->whereHas('account', static function ($query) {
                $query->where('type', 'ist');
            });

        if ($accounts) {
            $query->whereIn('account_id', $accounts->pluck('id'));
        }

        $entries = $query->get(['account_id', 'type', 'direction', 'amount', 'status']);

        $movements = [];
        foreach ($entries as $entry) {
            if ($entry->type === 'income' && $entry->status !== 'paid') {
                continue;
            }
            if (in_array($entry->type, ['expense', 'fixcost'], true) && $entry->status !== 'paid') {
                continue;
            }

            $sign = $entry->direction === 'out' ? -1 : 1;
            $movements[$entry->account_id] = round(
                ($movements[$entry->account_id] ?? 0) + $sign * (float) $entry->amount,
                2
            );
        }

        return $movements;
    }

    public function movementForAccount(Month $month, Account $account): float
    {
        if ($account->type !== 'ist') {
            return 0.0;
        }

        $movements = $this->movementsForMonth($month, collect([$account]));

        return (float) ($movements[$account->id] ?? 0);
    }

    public function balanceMetaForMonth(Month $month, Collection $accounts): array
    {
        $baseBalances = AccountBalance::forUser($month->user)
            ->orderByDesc('updated_at')
            ->get()
            ->unique('account_id')
            ->mapWithKeys(static fn (AccountBalance $balance) => [
                $balance->account_id => round((float) $balance->amount, 2),
            ]);

        $movements = $this->movementsForMonth($month, $accounts);

        $meta = [];
        foreach ($accounts as $account) {
            $base = (float) ($baseBalances[$account->id] ?? 0);
            $movement = (float) ($movements[$account->id] ?? 0);
            $effective = round($base + $movement, 2);
            $isRelevant = abs($base) > 0.00001 || abs($movement) > 0.00001;

            $meta[$account->id] = [
                'base' => $base,
                'movement' => $movement,
                'effective' => $effective,
                'is_relevant' => $isRelevant,
            ];
        }

        return $meta;
    }

    public function effectiveBalanceSum(Month $month): float
    {
        $accounts = $month->user->accounts()
            ->where('type', 'ist')
            ->get(['id', 'type']);

        $meta = $this->balanceMetaForMonth($month, $accounts);

        return round(
            array_reduce($meta, static fn ($carry, $item) => $carry + (float) $item['effective'], 0.0),
            2
        );
    }
}
