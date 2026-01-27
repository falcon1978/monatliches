<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomePaymentRequest;
use App\Http\Requests\StoreMonthEntryRequest;
use App\Http\Requests\TransferRequest;
use App\Models\Account;
use App\Models\Entry;
use App\Models\Month;
use App\Services\TransferService;
use Illuminate\Http\Request;

class MonthEntryController extends Controller
{
    public function index(Request $request, Month $month)
    {
        $this->authorize('view', $month);

        $query = $month->entries()
            ->where('user_id', $request->user()->id)
            ->with('account');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->integer('account_id'));
        }

        $entries = $query->orderByRaw('sort_order is null')
            ->orderBy('sort_order')
            ->orderBy('entry_date')
            ->get();
        $accounts = Account::forUser($request->user())->orderBy('name')->get();

        return view('months.entries.index', [
            'month' => $month,
            'entries' => $entries,
            'accounts' => $accounts,
            'filters' => $request->only(['type', 'status', 'account_id']),
        ]);
    }

    public function store(StoreMonthEntryRequest $request, Month $month)
    {
        $this->authorize('update', $month);

        $data = $request->validated();
        $type = $data['type'];
        $direction = $type === 'income' ? 'in' : 'out';
        $dueDate = $type === 'expense'
            ? ($data['due_date'] ?? $month->date_to->toDateString())
            : null;
        $monthForEntry = $month;
        $incomeSource = $data['income_source'] ?? null;

        if ($type === 'expense') {
            $monthForEntry = Month::forUser($request->user())
                ->whereDate('date_from', '<=', $dueDate)
                ->whereDate('date_to', '>=', $dueDate)
                ->first();

            if (! $monthForEntry) {
                return back()->withErrors(['due_date' => 'Kein Monat für das Fälligkeitsdatum vorhanden.']);
            }
        }

        if ($type === 'income' && empty($data['account_id'])) {
            $fallbackAccount = Account::forUser($request->user())
                ->where('type', 'forecast')
                ->orderBy('id')
                ->first();

            if (! $fallbackAccount) {
                return back()->withErrors(['account_id' => 'Kein Forecast-Konto vorhanden.']);
            }

            $data['account_id'] = $fallbackAccount->id;
        }

        if ($type === 'income' && $incomeSource === null) {
            $account = Account::forUser($request->user())->find($data['account_id']);
            if ($account) {
                $incomeSource = $account->type === 'forecast' ? 'expected' : 'manual';
            }
        }

        $sortOrder = Entry::query()
            ->where('month_id', $monthForEntry->id)
            ->where('type', $type)
            ->max('sort_order');

        Entry::create([
            'user_id' => $request->user()->id,
            'month_id' => $monthForEntry->id,
            'entry_date' => $data['entry_date'] ?? now()->toDateString(),
            'due_date' => $dueDate,
            'type' => $type,
            'income_source' => $incomeSource,
            'direction' => $direction,
            'amount' => $data['amount'],
            'account_id' => $data['account_id'] ?? null,
            'status' => $data['status'] ?? 'open',
            'description' => $data['description'],
            'sort_order' => is_null($sortOrder) ? 1 : $sortOrder + 1,
        ]);

        if ($monthForEntry->id !== $month->id) {
            return redirect()
                ->route('months.show', $monthForEntry)
                ->with('status', 'Eintrag im passenden Monat erstellt.');
        }

        return back()->with('status', 'Eintrag erstellt.');
    }

    public function receivePayment(
        IncomePaymentRequest $request,
        Month $month,
        TransferService $transferService
    ) {
        $this->authorize('update', $month);

        $income = Entry::forUser($request->user())
            ->where('month_id', $month->id)
            ->with(['account', 'relatedTransfersOut', 'recurringTemplate'])
            ->findOrFail($request->integer('entry_id'));

        if ($income->type !== 'income' || $income->direction !== 'in') {
            return back()->withErrors(['entry_id' => 'Ungültige Einnahme für die Zahlung.']);
        }

        $source = $income->income_source;
        if ($source === null) {
            $source = $income->recurring_template_id
                ? 'manual'
                : ($income->account?->type === 'forecast' ? 'expected' : 'manual');
        }
        $useTransfers = $source === 'expected' || $income->relatedTransfersOut->isNotEmpty();

        $openAmount = $income->open_amount;
        $amount = (float) $request->input('amount');

        if ($amount <= 0) {
            return back()->withErrors(['amount' => 'Betrag muss größer als 0 sein.']);
        }

        if ($amount > $openAmount) {
            return back()->withErrors(['amount' => 'Betrag ist höher als der offene Betrag.']);
        }

        $targetAccount = Account::forUser($request->user())
            ->where('type', 'ist')
            ->findOrFail($request->integer('target_account_id'));

        if ($useTransfers) {
            if (! $income->account || $income->account->type !== 'forecast') {
                return back()->withErrors(['entry_id' => 'Forecast-Konto fehlt für die Zahlung.']);
            }

            $wasPaid = $income->status === 'paid';

            $transferService->createTransfer(
                $request->user(),
                $month,
                $income->account,
                $targetAccount,
                $amount,
                'Zahlung eingegangen: ' . $income->description,
                $income
            );

            $income->refresh()->load('relatedTransfersOut');
            $income->status = $income->open_amount <= 0 ? 'paid' : 'partial';
            $income->save();

            if (! $wasPaid && $income->status === 'paid') {
                $this->adjustTemplateRemaining($income, -1 * (float) $income->amount);
            }
        } else {
            if ($amount < $openAmount) {
                return back()->withErrors(['amount' => 'Für diese Einnahme sind nur Gesamtzahlungen erlaubt.']);
            }

            $wasPaid = $income->status === 'paid';
            $income->account_id = $targetAccount->id;
            $income->status = 'paid';
            $income->save();

            if (! $wasPaid) {
                $this->adjustTemplateRemaining($income, -1 * (float) $income->amount);
            }
        }

        return back()->with('status', 'Zahlung verbucht.');
    }

    public function storeTransfer(
        TransferRequest $request,
        Month $month,
        TransferService $transferService
    ) {
        $this->authorize('update', $month);

        $fromAccount = Account::forUser($request->user())->findOrFail($request->integer('from_account_id'));
        $toAccount = Account::forUser($request->user())->findOrFail($request->integer('to_account_id'));

        $isValid = (
            ($fromAccount->type === 'ist' && $toAccount->type === 'clearing')
            || ($fromAccount->type === 'clearing' && $toAccount->type === 'ist')
        );

        if (! $isValid) {
            return back()->withErrors(['from_account_id' => 'Nur Transfers zwischen Bank/Bar und Partnerin Verrechnung erlaubt.']);
        }

        $transferService->createTransfer(
            $request->user(),
            $month,
            $fromAccount,
            $toAccount,
            (float) $request->input('amount'),
            $request->input('description')
        );

        return back()->with('status', 'Transfer erstellt.');
    }

    public function updateOrder(Request $request, Month $month)
    {
        $this->authorize('update', $month);

        $data = $request->validate([
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['integer'],
            'type' => ['nullable', 'in:income,expense,fixcost'],
        ]);

        $entries = Entry::forUser($request->user())
            ->where('month_id', $month->id)
            ->whereIn('id', $data['entry_ids'])
            ->get();

        if ($entries->count() !== count($data['entry_ids'])) {
            return response()->json(['message' => 'Ungültige Einträge für diesen Monat.'], 422);
        }

        if (! empty($data['type']) && $entries->contains(fn (Entry $entry) => $entry->type !== $data['type'])) {
            return response()->json(['message' => 'Einträge-Typ passt nicht.'], 422);
        }

        foreach ($data['entry_ids'] as $index => $entryId) {
            Entry::where('id', $entryId)->update(['sort_order' => $index + 1]);
        }

        return response()->json(['status' => 'ok']);
    }

    private function adjustTemplateRemaining(Entry $entry, float $delta): void
    {
        if (! $entry->recurring_template_id) {
            return;
        }

        $entry->loadMissing('recurringTemplate');
        $template = $entry->recurringTemplate;

        if (! $template || $template->remaining_amount === null) {
            return;
        }

        $newAmount = round((float) $template->remaining_amount + $delta, 2);
        if ($newAmount < 0) {
            $newAmount = 0;
        }

        $template->remaining_amount = $newAmount;
        $template->save();
    }
}
