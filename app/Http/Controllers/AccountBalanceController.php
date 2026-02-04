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
                'account_id' => $account->id,
            ],
            [
                'month_id' => $month->id,
                'amount' => $baseAmount,
            ]
        );

        return back()->with('status', 'Kontostand aktualisiert.');
    }

}
