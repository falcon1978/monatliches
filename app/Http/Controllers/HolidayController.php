<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use App\Models\Holiday;

class HolidayController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Holiday::class);

        $holidays = Holiday::forUser(request()->user())
            ->orderBy('date_from')
            ->orderBy('date_to')
            ->get();

        return view('holidays.index', [
            'holidays' => $holidays,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Holiday::class);

        return view('holidays.create');
    }

    public function store(StoreHolidayRequest $request)
    {
        $data = $request->validated();
        $customLivingCost = $data['living_cost_mode'] === 'custom'
            ? ($data['custom_living_cost'] ?? null)
            : null;

        Holiday::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'] ?? null,
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'living_cost_mode' => $data['living_cost_mode'],
            'custom_living_cost' => $customLivingCost,
        ]);

        return redirect()
            ->route('holidays.index')
            ->with('status', 'Ferien erfasst.');
    }

    public function edit(Holiday $holiday)
    {
        $this->authorize('update', $holiday);

        return view('holidays.edit', [
            'holiday' => $holiday,
        ]);
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday)
    {
        $data = $request->validated();
        $customLivingCost = $data['living_cost_mode'] === 'custom'
            ? ($data['custom_living_cost'] ?? null)
            : null;

        $holiday->update([
            'name' => $data['name'] ?? null,
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'living_cost_mode' => $data['living_cost_mode'],
            'custom_living_cost' => $customLivingCost,
        ]);

        return redirect()
            ->route('holidays.index')
            ->with('status', 'Ferien aktualisiert.');
    }

    public function destroy(Holiday $holiday)
    {
        $this->authorize('delete', $holiday);

        $holiday->delete();

        return redirect()
            ->route('holidays.index')
            ->with('status', 'Ferien gelöscht.');
    }
}
