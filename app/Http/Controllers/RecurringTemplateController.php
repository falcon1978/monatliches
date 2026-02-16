<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringTemplateRequest;
use App\Http\Requests\UpdateRecurringTemplateRequest;
use App\Models\Entry;
use App\Models\Month;
use App\Models\RecurringTemplate;
use Illuminate\Http\Request;

class RecurringTemplateController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', RecurringTemplate::class);

        $templates = RecurringTemplate::forUser(request()->user())
            ->orderByRaw('sort_order is null')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('recurring_templates.index', [
            'templates' => $templates,
        ]);
    }

    public function create()
    {
        $this->authorize('create', RecurringTemplate::class);

        return view('recurring_templates.create');
    }

    public function store(StoreRecurringTemplateRequest $request)
    {
        $data = $request->validated();
        $months = collect($data['months'] ?? [])
            ->map(static fn ($month) => (int) $month)
            ->filter(static fn ($month) => $month >= 1 && $month <= 12)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $monthsMask = implode(',', $months);

        $sortOrder = RecurringTemplate::forUser($request->user())
            ->where('kind', $data['kind'])
            ->max('sort_order');

        $remainingAmount = $data['kind'] === 'fixcost' ? ($data['remaining_amount'] ?? null) : null;
        if ($remainingAmount === '') {
            $remainingAmount = null;
        }
        $endsOn = $data['kind'] === 'fixcost' ? ($data['ends_on'] ?? null) : null;
        if ($endsOn === '') {
            $endsOn = null;
        }

        $template = RecurringTemplate::create([
            'user_id' => $request->user()->id,
            'kind' => $data['kind'],
            'name' => $data['name'],
            'amount' => $data['amount'],
            'remaining_amount' => $remainingAmount,
            'ends_on' => $endsOn,
            'currency' => 'CHF',
            'frequency' => 'custom_months',
            'months_mask' => $monthsMask,
            'default_account_id' => null,
            'is_active' => true,
            'sort_order' => is_null($sortOrder) ? 1 : $sortOrder + 1,
        ]);

        $this->importTemplateIntoFutureMonths($template);

        return redirect()
            ->route('recurring-templates.index')
            ->with('status', 'Wiederkehrender Posten erstellt.');
    }

    public function edit(RecurringTemplate $recurringTemplate)
    {
        $this->authorize('update', $recurringTemplate);

        return view('recurring_templates.edit', [
            'template' => $recurringTemplate,
        ]);
    }

    public function update(UpdateRecurringTemplateRequest $request, RecurringTemplate $recurringTemplate)
    {
        $data = $request->validated();
        $months = collect($data['months'] ?? [])
            ->map(static fn ($month) => (int) $month)
            ->filter(static fn ($month) => $month >= 1 && $month <= 12)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $monthsMask = implode(',', $months);

        $remainingAmount = $data['kind'] === 'fixcost' ? ($data['remaining_amount'] ?? null) : null;
        if ($remainingAmount === '') {
            $remainingAmount = null;
        }
        $endsOn = $data['kind'] === 'fixcost' ? ($data['ends_on'] ?? null) : null;
        if ($endsOn === '') {
            $endsOn = null;
        }

        $recurringTemplate->update([
            'kind' => $data['kind'],
            'name' => $data['name'],
            'amount' => $data['amount'],
            'remaining_amount' => $remainingAmount,
            'ends_on' => $endsOn,
            'currency' => $recurringTemplate->currency ?? 'CHF',
            'frequency' => 'custom_months',
            'months_mask' => $monthsMask,
            'default_account_id' => null,
            'is_active' => true,
        ]);

        $this->importTemplateIntoFutureMonths($recurringTemplate);

        return redirect()
            ->route('recurring-templates.index')
            ->with('status', 'Wiederkehrender Posten aktualisiert.');
    }

    public function destroy(RecurringTemplate $recurringTemplate)
    {
        $this->authorize('delete', $recurringTemplate);
        $recurringTemplate->delete();

        return redirect()
            ->route('recurring-templates.index')
            ->with('status', 'Wiederkehrender Posten gelöscht.');
    }

    public function updateOrder(Request $request)
    {
        $this->authorize('viewAny', RecurringTemplate::class);

        $data = $request->validate([
            'template_ids' => ['required', 'array', 'min:1'],
            'template_ids.*' => ['integer'],
            'kind' => ['required', 'in:income,fixcost'],
        ]);

        $templates = RecurringTemplate::forUser($request->user())
            ->whereIn('id', $data['template_ids'])
            ->get();

        if ($templates->count() !== count($data['template_ids'])) {
            return response()->json(['message' => 'Ungültige Vorlagen für diesen Benutzer.'], 422);
        }

        if ($templates->contains(function (RecurringTemplate $template) use ($data) {
            if ($data['kind'] === 'fixcost' && $template->kind === 'expense') {
                return false;
            }

            return $template->kind !== $data['kind'];
        })) {
            return response()->json(['message' => 'Vorlagen-Typ passt nicht.'], 422);
        }

        foreach ($data['template_ids'] as $index => $templateId) {
            RecurringTemplate::where('id', $templateId)->update(['sort_order' => $index + 1]);
        }

        return response()->json(['status' => 'ok']);
    }

    private function importTemplateIntoFutureMonths(RecurringTemplate $template): int
    {
        $user = $template->user;
        $today = now()->startOfDay();
        $months = Month::forUser($user)
            ->visible()
            ->whereDate('date_to', '>=', $today->toDateString())
            ->orderBy('date_from')
            ->get();

        if ($months->isEmpty()) {
            return 0;
        }

        if ($template->remaining_amount !== null && (float) $template->remaining_amount <= 0) {
            return 0;
        }

        $accounts = $user->accounts()->get()->groupBy('type');
        $created = 0;

        foreach ($months as $month) {
            if (! $template->appliesToMonth($month)) {
                continue;
            }

            $exists = Entry::query()
                ->where('month_id', $month->id)
                ->where(function ($query) use ($template) {
                    $query->where('recurring_template_id', $template->id)
                        ->orWhere(function ($query) use ($template) {
                            $query->where('type', $template->kind)
                                ->where('description', $template->name)
                                ->where('amount', $template->amount);
                        });
                })
                ->exists();

            if ($exists) {
                continue;
            }

            $account = $template->defaultAccount;
            if ($template->kind === 'income' && $account && ! in_array($account->type, ['forecast', 'clearing'], true)) {
                $account = null;
            }
            $account = $account
                ?? ($template->kind === 'income'
                    ? ($accounts->get('forecast')?->first() ?? $accounts->get('clearing')?->first())
                    : $accounts->get('ist')?->first())
                ?? $user->accounts()->first();

            if (! $account) {
                continue;
            }

            $amount = (float) $template->amount;
            if ($template->remaining_amount !== null) {
                $amount = min($amount, (float) $template->remaining_amount);
            }

            $maxOrder = Entry::query()
                ->where('month_id', $month->id)
                ->where('type', $template->kind)
                ->max('sort_order');
            $sortOrder = ($maxOrder ?? 0) + 1;

            Entry::create([
                'user_id' => $user->id,
                'month_id' => $month->id,
                'entry_date' => $month->date_from->toDateString(),
                'due_date' => null,
                'type' => $template->kind,
                'income_source' => $template->kind === 'income' ? 'manual' : null,
                'direction' => $template->kind === 'income' ? 'in' : 'out',
                'amount' => $amount,
                'account_id' => $account->id,
                'status' => 'open',
                'description' => $template->name,
                'recurring_template_id' => $template->id,
                'sort_order' => $sortOrder,
            ]);

            $created++;
        }

        return $created;
    }
}
