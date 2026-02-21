<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\Entry;
use App\Models\Month;
use App\Services\AccountBalanceService;
use App\Models\User;

class AccountController extends Controller
{
    public function index(AccountBalanceService $balanceService)
    {
        $this->authorize('viewAny', Account::class);

        $user = request()->user();

        $accounts = Account::forUser($user)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $currentMonth = $this->resolveCurrentMonthForUser($user);

        $forecastBalances = collect();
        $accountBalances = collect();
        $balanceMeta = [];

        if ($currentMonth) {
            $forecastBalances = Entry::query()
                ->where('user_id', $user->id)
                ->where('month_id', $currentMonth->id)
                ->where('type', 'income')
                ->whereIn('status', ['open', 'partial'])
                ->where(function ($query) {
                    $query->where('income_source', 'expected')
                        ->orWhere(function ($query) {
                            $query->whereNull('income_source')
                                ->whereNull('recurring_template_id')
                                ->whereHas('account', static function ($query) {
                                    $query->whereIn('type', ['forecast', 'clearing']);
                                });
                        });
                })
                ->whereHas('account', static function ($query) {
                    $query->whereIn('type', ['forecast', 'clearing']);
                })
                ->with('relatedTransfersOut')
                ->get()
                ->groupBy('account_id')
                ->map(static fn ($entries) => round($entries->sum(static fn (Entry $entry) => $entry->open_amount), 2));

            $balanceAccounts = $accounts->where('type', 'ist');
            $balanceMeta = $balanceService->balanceMetaForMonth($currentMonth, $balanceAccounts);
            $accountBalances = collect($balanceMeta)->mapWithKeys(static function ($meta, $accountId) {
                return [$accountId => $meta['effective']];
            });
        }

        return view('accounts.index', [
            'accounts' => $accounts,
            'forecastBalances' => $forecastBalances,
            'accountBalances' => $accountBalances,
            'balanceMeta' => $balanceMeta,
            'currentMonth' => $currentMonth,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Account::class);

        $user = request()->user();

        return view('accounts.create', [
            'currentMonth' => $this->resolveCurrentMonthForUser($user),
        ]);
    }

    public function store(StoreAccountRequest $request)
    {
        $account = Account::create([
            'user_id' => $request->user()->id,
            'name' => $request->input('name'),
            'type' => $request->input('type'),
        ]);

        $initialBalance = $request->input('initial_balance');
        if ($initialBalance !== null && $account->type === 'ist') {
            $currentMonth = $this->resolveCurrentMonthForUser($request->user());
            if ($currentMonth) {
                AccountBalance::updateOrCreate(
                    [
                        'user_id' => $request->user()->id,
                        'account_id' => $account->id,
                    ],
                    [
                        'month_id' => $currentMonth->id,
                        'amount' => round((float) $initialBalance, 2),
                    ]
                );
            }
        }

        return redirect()->route('accounts.index')->with('status', 'Konto erstellt.');
    }

    public function edit(Account $account)
    {
        $this->authorize('update', $account);

        return view('accounts.edit', [
            'account' => $account,
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account)
    {
        $account->update($request->validated());

        return redirect()->route('accounts.index')->with('status', 'Konto aktualisiert.');
    }

    public function destroy(Account $account)
    {
        $this->authorize('delete', $account);

        if ($account->entries()->exists()) {
            return back()->withErrors(['account' => 'Konto hat Einträge und kann nicht gelöscht werden.']);
        }

        $account->delete();

        return redirect()->route('accounts.index')->with('status', 'Konto gelöscht.');
    }

    private function resolveCurrentMonthForUser(User $user): ?Month
    {
        $months = Month::forUser($user)
            ->visible()
            ->orderBy('date_from')
            ->get();

        if ($months->isEmpty()) {
            return null;
        }

        $currentMonth = $months->firstWhere('is_current', true);
        if ($currentMonth) {
            return $currentMonth;
        }

        $today = now()->startOfDay();
        $currentIndex = $months->search(fn (Month $month) => $today->between($month->date_from, $month->date_to, true));

        if ($currentIndex === false) {
            $currentIndex = $months->search(fn (Month $month) => $month->date_from->gte($today));
        }

        if ($currentIndex === false) {
            $currentIndex = $months->count() ? $months->count() - 1 : null;
        }

        return $currentIndex !== null ? $months->get($currentIndex) : null;
    }
}
