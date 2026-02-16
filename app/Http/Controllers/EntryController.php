<?php

namespace App\Http\Controllers;

use App\Http\Requests\FixcostPaymentRequest;
use App\Http\Requests\UpdateEntryRequest;
use App\Models\Account;
use App\Models\Entry;
use App\Models\Month;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EntryController extends Controller
{
    public function edit(Entry $entry)
    {
        $this->authorize('update', $entry);

        $accounts = Account::forUser(request()->user())
            ->orderBy('name')
            ->get();

        return view('entries.edit', [
            'entry' => $entry,
            'accounts' => $accounts,
        ]);
    }

    public function update(UpdateEntryRequest $request, Entry $entry)
    {
        $originalStatus = $entry->status;
        $originalAmount = (float) $entry->amount;

        $data = $request->validated();

        if ($entry->type === 'expense') {
            $dueDate = $data['due_date'] ?? $entry->due_date?->toDateString() ?? $entry->entry_date->toDateString();
            $data['due_date'] = $dueDate;

            $targetMonth = Month::forUser($request->user())
                ->whereDate('date_from', '<=', $dueDate)
                ->whereDate('date_to', '>=', $dueDate)
                ->first();

            if (! $targetMonth) {
                return back()->withErrors(['due_date' => 'Kein Monat für das Fälligkeitsdatum vorhanden.']);
            }

            if ($entry->month_id !== $targetMonth->id) {
                $sortOrder = Entry::query()
                    ->where('month_id', $targetMonth->id)
                    ->where('type', 'expense')
                    ->max('sort_order');

                $data['month_id'] = $targetMonth->id;
                $data['sort_order'] = is_null($sortOrder) ? 1 : $sortOrder + 1;
            }
        }

        $entry->update($data);
        $entry->refresh()->load(['account', 'relatedTransfersOut']);

        if (
            $entry->type === 'income'
            && (
                $entry->income_source === 'expected'
                || in_array($entry->account?->type, ['forecast', 'clearing'], true)
            )
        ) {
            $openAmount = $entry->open_amount;
            $calculatedStatus = $openAmount <= 0
                ? 'paid'
                : ($openAmount < (float) $entry->amount ? 'partial' : 'open');

            if ($entry->status !== $calculatedStatus) {
                $entry->status = $calculatedStatus;
                $entry->save();
            }
        }

        if ($entry->recurring_template_id) {
            if ($originalStatus !== 'paid' && $entry->status === 'paid') {
                $this->adjustTemplateRemaining($entry, -1 * (float) $entry->amount);
            } elseif ($originalStatus === 'paid' && $entry->status !== 'paid') {
                $this->adjustTemplateRemaining($entry, $originalAmount);
            } elseif ($originalStatus === 'paid' && $entry->status === 'paid') {
                $delta = $originalAmount - (float) $entry->amount;
                if (abs($delta) > 0.00001) {
                    $this->adjustTemplateRemaining($entry, $delta);
                }
            }
        }

        return redirect()
            ->route('months.show', $entry->month_id)
            ->with('status', 'Eintrag aktualisiert.');
    }

    public function togglePaid(Entry $entry)
    {
        $this->authorize('update', $entry);

        if ($entry->type !== 'fixcost') {
            return back()->withErrors(['status' => 'Nur Fixkosten können so umgeschaltet werden.']);
        }

        $wasPaid = $entry->status === 'paid';
        $entry->status = $wasPaid ? 'open' : 'paid';
        $entry->save();

        if (! $wasPaid && $entry->status === 'paid') {
            $this->adjustTemplateRemaining($entry, -1 * (float) $entry->amount);
        }

        if ($wasPaid && $entry->status === 'open') {
            $this->adjustTemplateRemaining($entry, (float) $entry->amount);
        }

        return back()->with('status', 'Fixkosten-Status aktualisiert.');
    }

    public function payFixcost(FixcostPaymentRequest $request, Entry $entry): RedirectResponse
    {
        $this->authorize('update', $entry);

        if (! in_array($entry->type, ['fixcost', 'expense'], true)) {
            return back()->withErrors(['status' => 'Nur Fixkosten oder Rechnungen können so bezahlt werden.']);
        }

        $wasPaid = $entry->status === 'paid';

        $account = Account::forUser($request->user())
            ->whereIn('type', ['ist', 'clearing'])
            ->findOrFail($request->integer('account_id'));

        $entry->status = 'paid';
        $entry->account_id = $account->id;
        $entry->save();

        if (! $wasPaid) {
            $this->adjustTemplateRemaining($entry, -1 * (float) $entry->amount);
        }

        $message = $entry->type === 'expense' ? 'Rechnung bezahlt.' : 'Fixkosten bezahlt.';

        return back()->with('status', $message);
    }

    public function moveToNextMonth(Request $request, Entry $entry): RedirectResponse
    {
        return $this->moveToAdjacentMonth($request, $entry, 'next');
    }

    public function moveToPrevMonth(Request $request, Entry $entry): RedirectResponse
    {
        return $this->moveToAdjacentMonth($request, $entry, 'prev');
    }

    public function destroy(Request $request, Entry $entry): RedirectResponse
    {
        $this->authorize('delete', $entry);

        if ($entry->type === 'transfer') {
            $groupId = $entry->transfer_group_id;
            $relatedIncomeIds = collect();

            if ($groupId) {
                $relatedIncomeIds = Entry::query()
                    ->where('transfer_group_id', $groupId)
                    ->where('user_id', $entry->user_id)
                    ->pluck('related_entry_id')
                    ->filter()
                    ->unique()
                    ->values();

                Entry::where('transfer_group_id', $groupId)
                    ->where('user_id', $entry->user_id)
                    ->delete();
            } else {
                $relatedIncomeIds = collect([$entry->related_entry_id])->filter();
                $entry->delete();
            }

            $relatedIncomeIds->each(function ($incomeId) {
                $this->refreshIncomeStatus($incomeId);
            });

            if ($request->boolean('redirect_to_month')) {
                return redirect()
                    ->route('months.show', $entry->month_id)
                    ->with('status', 'Transfer gelöscht.');
            }

            return back()->with('status', 'Transfer gelöscht.');
        }

        if ($entry->type === 'income') {
            $groupIds = Entry::query()
                ->where('related_entry_id', $entry->id)
                ->where('type', 'transfer')
                ->where('user_id', $entry->user_id)
                ->pluck('transfer_group_id')
                ->filter()
                ->unique();

            if ($groupIds->isNotEmpty()) {
                Entry::whereIn('transfer_group_id', $groupIds)
                    ->where('user_id', $entry->user_id)
                    ->delete();
            }
        }

        if ($entry->status === 'paid') {
            $this->adjustTemplateRemaining($entry, (float) $entry->amount);
        }

        $entry->delete();

        $route = $request->boolean('redirect_to_month')
            ? route('months.show', $entry->month_id)
            : route('months.entries.index', $entry->month_id);

        return redirect($route)->with('status', 'Eintrag gelöscht.');
    }

    private function refreshIncomeStatus(int $incomeId): void
    {
        $income = Entry::query()
            ->where('type', 'income')
            ->where('id', $incomeId)
            ->with('relatedTransfersOut')
            ->first();

        if (! $income) {
            return;
        }

        $openAmount = $income->open_amount;
        $income->status = $openAmount <= 0
            ? 'paid'
            : ($openAmount < (float) $income->amount ? 'partial' : 'open');
        $income->save();
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

    private function moveToAdjacentMonth(Request $request, Entry $entry, string $direction): RedirectResponse
    {
        $this->authorize('update', $entry);

        if ($entry->type === 'transfer') {
            return back()->withErrors(['entry' => 'Transfers können nicht verschoben werden.']);
        }

        if (! in_array($direction, ['prev', 'next'], true)) {
            return back()->withErrors(['entry' => 'Ungültige Richtung.']);
        }

        $currentMonth = Month::forUser($request->user())->find($entry->month_id);
        if (! $currentMonth) {
            return back()->withErrors(['entry' => 'Monat nicht gefunden.']);
        }

        $targetStart = $direction === 'next'
            ? $currentMonth->date_from->copy()->addMonthNoOverflow()->startOfMonth()
            : $currentMonth->date_from->copy()->subMonthNoOverflow()->startOfMonth();

        $targetMonth = Month::forUser($request->user())
            ->whereDate('date_from', $targetStart->toDateString())
            ->first();

        if (! $targetMonth) {
            return back()->withErrors(['entry' => 'Zielmonat nicht vorhanden.']);
        }

        $update = [
            'month_id' => $targetMonth->id,
            'moved_from_month_id' => $currentMonth->id,
        ];

        if (! $entry->origin_month_id) {
            $update['origin_month_id'] = $currentMonth->id;
        }

        if ($entry->type === 'expense') {
            $baseDate = $entry->due_date ?? $entry->entry_date;
            $targetDue = $direction === 'next'
                ? $baseDate->copy()->addMonthNoOverflow()
                : $baseDate->copy()->subMonthNoOverflow();

            $update['due_date'] = $targetDue->toDateString();
        } else {
            $update['entry_date'] = $targetMonth->date_from->toDateString();
        }

        $sortOrder = Entry::query()
            ->where('month_id', $targetMonth->id)
            ->where('type', $entry->type)
            ->max('sort_order');

        $update['sort_order'] = is_null($sortOrder) ? 1 : $sortOrder + 1;

        $entry->update($update);

        return back()->with('status', 'Eintrag verschoben.');
    }
}
