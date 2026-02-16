<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Eintrag bearbeiten</h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10 space-y-4">
            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box">
                <div class="p-6">
                    @if ($entry->recurringTemplate)
                        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 text-xs text-amber-900">
                            Änderung gilt nur für diesen Monat. Willst du die {{ $entry->type === 'income' ? 'Einnahme' : 'Ausgabe' }} generell anpassen,
                            <a href="{{ route('recurring-templates.edit', $entry->recurringTemplate) }}" class="font-semibold underline">klicke hier</a>.
                        </div>
                    @endif
                    <form method="POST" action="{{ route('entries.update', $entry) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="entry_date" value="Datum" />
                            <x-text-input id="entry_date" name="entry_date" type="date" class="mt-1 block w-full" value="{{ old('entry_date', $entry->entry_date->format('Y-m-d')) }}" required />
                            <x-input-error :messages="$errors->get('entry_date')" class="mt-2" />
                        </div>

                        @if ($entry->type === 'expense')
                            <div>
                                <x-input-label for="due_date" value="Fälligkeitsdatum" />
                                <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" value="{{ old('due_date', $entry->due_date?->format('Y-m-d') ?? $entry->entry_date->format('Y-m-d')) }}" required />
                                <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                            </div>
                        @endif

                        <div>
                            <x-input-label for="description" value="Beschreibung" />
                            <x-text-input id="description" name="description" type="text" class="mt-1 block w-full" value="{{ old('description', $entry->description) }}" required />
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="amount" value="Betrag" />
                                <x-text-input id="amount" name="amount" type="number" step="0.01" class="mt-1 block w-full" value="{{ old('amount', $entry->amount) }}" required />
                                <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="status" value="Status" />
                                <select id="status" name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                                    @php
                                        $statusOptions = $entry->type === 'income'
                                            ? ['open' => 'Offen', 'partial' => 'Teilbezahlt', 'paid' => 'Bezahlt']
                                            : ['open' => 'Offen', 'paid' => 'Bezahlt'];
                                    @endphp
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', $entry->status) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>
                        </div>

                        @php
                            $showAccountSelect = $entry->type !== 'income'
                                || $entry->income_source === 'expected'
                                || ($entry->account && ! in_array($entry->account->type, ['forecast', 'clearing'], true));
                        @endphp
                        @if ($showAccountSelect)
                            <div>
                                <x-input-label for="account_id" value="Konto" />
                                <select id="account_id" name="account_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}" @selected(old('account_id', $entry->account_id) == $account->id)>{{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
                            </div>
                        @else
                            <input type="hidden" name="account_id" value="{{ $entry->account_id }}">
                            <div class="text-sm text-gray-600">Konto wird bei der Zahlung bestimmt.</div>
                        @endif

                        <div class="flex justify-end gap-3">
                            <a href="{{ route('months.show', $entry->month_id) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm">Abbrechen</a>
                            <x-primary-button>Speichern</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box">
                <div class="p-6">
                    <form method="POST" action="{{ route('entries.destroy', $entry) }}" onsubmit="return confirm('Eintrag wirklich löschen?');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Eintrag löschen</x-danger-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
