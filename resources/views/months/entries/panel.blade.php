@php
    $embedded = $embedded ?? false;
    $filters = $filters ?? [];
@endphp

@if ($embedded)
    <div class="flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-900">Einträge</h3>
        <button type="button" class="text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-800" @click="entriesOpen = false">Schliessen</button>
    </div>
@endif

<div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg p-6 space-y-4 border accent-box">
    <h3 class="text-lg font-semibold text-gray-800">Filter</h3>
    <form method="GET" action="{{ $embedded ? route('months.show', $month) : route('months.entries.index', $month) }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
        @if ($embedded)
            <input type="hidden" name="entries" value="1">
        @endif
        <select name="type" class="border-gray-300 rounded-md shadow-sm">
            <option value="">Typ</option>
            <option value="income" @selected(($filters['type'] ?? '') === 'income')>Einnahme</option>
            <option value="expense" @selected(($filters['type'] ?? '') === 'expense')>Ausgabe</option>
            <option value="fixcost" @selected(($filters['type'] ?? '') === 'fixcost')>Fixkosten</option>
            <option value="transfer" @selected(($filters['type'] ?? '') === 'transfer')>Transfer</option>
        </select>
        <select name="status" class="border-gray-300 rounded-md shadow-sm">
            <option value="">Status</option>
            <option value="open" @selected(($filters['status'] ?? '') === 'open')>Offen</option>
            <option value="partial" @selected(($filters['status'] ?? '') === 'partial')>Teilbezahlt</option>
            <option value="paid" @selected(($filters['status'] ?? '') === 'paid')>Bezahlt</option>
        </select>
        <select name="account_id" class="border-gray-300 rounded-md shadow-sm">
            <option value="">Konto</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected(($filters['account_id'] ?? '') == $account->id)>
                    {{ $account->name }}
                </option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <x-secondary-button type="submit">Filtern</x-secondary-button>
            @if ($embedded)
                <a href="{{ route('months.show', ['month' => $month, 'entries' => 1]) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm">Reset</a>
            @else
                <a href="{{ route('months.entries.index', $month) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm">Reset</a>
            @endif
        </div>
    </form>
</div>

<div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg p-6 space-y-4 border accent-box">
    <h3 class="text-lg font-semibold text-gray-800">Neuer Eintrag</h3>
    @php
        $defaultForecastAccount = $accounts->firstWhere('type', 'forecast');
    @endphp
    <form method="POST" action="{{ route('months.entries.store', $month) }}" class="grid grid-cols-1 md:grid-cols-8 gap-3" x-data="{ type: 'income', incomeSource: 'expected' }">
        @csrf
        <input type="date" name="entry_date" class="border-gray-300 rounded-md shadow-sm" value="{{ now()->format('Y-m-d') }}">
        <input type="date" name="due_date" class="border-gray-300 rounded-md shadow-sm" value="{{ $month->date_to->format('Y-m-d') }}" placeholder="Fällig">
        <select name="type" class="border-gray-300 rounded-md shadow-sm" x-model="type" required>
            <option value="income">Einnahme</option>
            <option value="expense">Ausgabe</option>
            <option value="fixcost">Fixkosten</option>
        </select>
        <select name="income_source" class="border-gray-300 rounded-md shadow-sm" x-model="incomeSource" x-show="type === 'income'" x-cloak x-bind:disabled="type !== 'income'">
            <option value="expected">Erwartet (Forecast)</option>
            <option value="manual">Manuell</option>
        </select>
        <input type="text" name="description" class="border-gray-300 rounded-md shadow-sm" placeholder="Beschreibung" required>
        <input type="number" step="0.01" name="amount" class="border-gray-300 rounded-md shadow-sm" placeholder="Betrag" required>
        <select name="account_id" class="border-gray-300 rounded-md shadow-sm" x-show="type !== 'income' || incomeSource === 'expected'" x-cloak x-bind:required="type !== 'income' || incomeSource === 'expected'" x-bind:disabled="type === 'income' && incomeSource === 'manual'">
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}">{{ $account->name }}</option>
            @endforeach
        </select>
        @if ($defaultForecastAccount)
            <input type="hidden" name="account_id" value="{{ $defaultForecastAccount->id }}" x-bind:disabled="type !== 'income' || incomeSource === 'expected'">
        @endif
        <div>
            <x-primary-button>Hinzufügen</x-primary-button>
        </div>
    </form>
</div>

@php
    $statusLabels = ['open' => 'Offen', 'partial' => 'Teilbezahlt', 'paid' => 'Bezahlt'];
    $typeLabels = ['income' => 'Einnahme', 'expense' => 'Ausgabe', 'fixcost' => 'Fixkosten', 'transfer' => 'Transfer'];
    $fmt = fn ($value) => new \Illuminate\Support\HtmlString(
        number_format((float) $value, 2, '.', "'")
    );
@endphp

<div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg p-6 border accent-box">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">Liste</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="py-2">Datum</th>
                    <th>Fällig</th>
                    <th>Typ</th>
                    <th>Beschreibung</th>
                    <th>Konto</th>
                    <th class="text-right">Betrag</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($entries as $entry)
                    @php
                        $accountLabel = $entry->account?->name ?? '–';
                        if ($entry->type === 'income') {
                            $source = $entry->income_source
                                ?? ($entry->recurring_template_id ? 'manual' : ($entry->account?->type === 'forecast' ? 'expected' : 'manual'));

                            if ($source !== 'expected' && $entry->account?->type === 'forecast') {
                                $accountLabel = 'Konto bei Zahlung';
                            }
                        }
                    @endphp
                    <tr>
                        <td class="py-2">{{ $entry->entry_date->format('d.m.Y') }}</td>
                        <td>{{ $entry->due_date?->format('d.m.Y') ?? '–' }}</td>
                        <td>{{ $typeLabels[$entry->type] ?? $entry->type }}</td>
                        <td>{{ $entry->description }}</td>
                        <td>{{ $accountLabel }}</td>
                        <td class="text-right tabular-nums font-semibold">CHF {{ $fmt($entry->amount) }}</td>
                        <td>{{ $statusLabels[$entry->status] ?? $entry->status }}</td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-3">
                                @if ($entry->type !== 'transfer')
                                    <a href="{{ route('entries.edit', $entry) }}" class="text-sm text-gray-700 underline">Bearbeiten</a>
                                @endif
                                @if ($entry->type !== 'transfer')
                                    <form method="POST" action="{{ route('entries.move-prev-month', $entry) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-sm text-[var(--accent)] underline">Vorheriger Monat</button>
                                    </form>
                                    <form method="POST" action="{{ route('entries.move-next-month', $entry) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-sm text-[var(--accent)] underline">Nächster Monat</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('entries.destroy', $entry) }}" onsubmit="return confirm('Eintrag wirklich löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-700 underline">Löschen</button>
                                </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
