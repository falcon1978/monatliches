<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Entry;
use App\Models\Month;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransferService
{
    public function createTransfer(
        User $user,
        Month $month,
        Account $from,
        Account $to,
        float $amount,
        string $description,
        ?Entry $relatedEntry = null
    ): array {
        $groupId = (string) Str::uuid();

        $outEntry = Entry::create([
            'user_id' => $user->id,
            'month_id' => $month->id,
            'entry_date' => now()->toDateString(),
            'type' => 'transfer',
            'direction' => 'out',
            'amount' => $amount,
            'account_id' => $from->id,
            'status' => 'paid',
            'description' => $description,
            'related_entry_id' => $relatedEntry?->id,
            'transfer_group_id' => $groupId,
        ]);

        $inEntry = Entry::create([
            'user_id' => $user->id,
            'month_id' => $month->id,
            'entry_date' => now()->toDateString(),
            'type' => 'transfer',
            'direction' => 'in',
            'amount' => $amount,
            'account_id' => $to->id,
            'status' => 'paid',
            'description' => $description,
            'related_entry_id' => $relatedEntry?->id,
            'transfer_group_id' => $groupId,
        ]);

        Log::info('Transfer created', [
            'user_id' => $user->id,
            'month_id' => $month->id,
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => $amount,
            'transfer_group_id' => $groupId,
            'related_entry_id' => $relatedEntry?->id,
        ]);

        return [$outEntry, $inEntry];
    }
}
