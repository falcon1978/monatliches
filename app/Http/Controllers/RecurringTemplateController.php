<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringTemplateRequest;
use App\Http\Requests\UpdateRecurringTemplateRequest;
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

        RecurringTemplate::create([
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
}
