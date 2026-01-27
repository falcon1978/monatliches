<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\Month;
use App\Services\AccountBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountBalanceController extends Controller
{
    public function update(
        Request $request,
        Month $month,
        Account $account,
        AccountBalanceService $balanceService
    ): RedirectResponse
    {
        $user = $request->user();

        if ($month->user_id !== $user->id || $account->user_id !== $user->id) {
            abort(403);
        }

        if (! in_array($account->type, ['ist', 'clearing'], true)) {
            return back()->withErrors(['balance' => 'Nur Ist- oder Verrechnungskonten können einen Kontostand haben.']);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric'],
        ]);

        $movement = $balanceService->movementForAccount($month, $account);
        $baseAmount = round((float) $data['amount'] - $movement, 2);
        if (abs($baseAmount) < 0.00001) {
            $baseAmount = 0.0;
        }

        AccountBalance::updateOrCreate(
            [
                'user_id' => $user->id,
                'month_id' => $month->id,
                'account_id' => $account->id,
            ],
            ['amount' => $baseAmount]
        );

        return back()->with('status', 'Kontostand aktualisiert.');
    }

    public function move(
        Request $request,
        Month $month,
        Account $account,
        string $direction,
        AccountBalanceService $balanceService
    ): RedirectResponse
    {
        $user = $request->user();

        if ($month->user_id !== $user->id || $account->user_id !== $user->id) {
            abort(403);
        }

        if (! in_array($account->type, ['ist', 'clearing'], true)) {
            return back()->withErrors(['balance' => 'Nur Ist- oder Verrechnungskonten können verschoben werden.']);
        }

        if (! in_array($direction, ['prev', 'next'], true)) {
            return back()->withErrors(['balance' => 'Ungültige Richtung.']);
        }

        $current = AccountBalance::query()
            ->where('user_id', $user->id)
            ->where('month_id', $month->id)
            ->where('account_id', $account->id)
            ->first();

        $currentBase = (float) ($current?->amount ?? 0);
        $currentMovement = $balanceService->movementForAccount($month, $account);
        $currentEffective = round($currentBase + $currentMovement, 2);

        if (abs($currentEffective) < 0.00001) {
            return back()->withErrors(['balance' => 'Kein Kontostand zum Verschieben vorhanden.']);
        }

        $targetStart = $direction === 'next'
            ? $month->date_from->copy()->addMonthNoOverflow()->startOfMonth()
            : $month->date_from->copy()->subMonthNoOverflow()->startOfMonth();

        $targetMonth = Month::forUser($user)
            ->whereDate('date_from', $targetStart->toDateString())
            ->first();

        if (! $targetMonth) {
            return back()->withErrors(['balance' => 'Zielmonat nicht vorhanden.']);
        }

        $targetMovement = $balanceService->movementForAccount($targetMonth, $account);
        $targetBase = round($currentEffective - $targetMovement, 2);
        if (abs($targetBase) < 0.00001) {
            $targetBase = 0.0;
        }

        $target = AccountBalance::firstOrNew([
            'user_id' => $user->id,
            'month_id' => $targetMonth->id,
            'account_id' => $account->id,
        ]);

        $target->amount = $targetBase;
        $target->save();
        if ($current) {
            $current->delete();
        }

        return back()->with('status', 'Kontostand verschoben.');
    }
}
