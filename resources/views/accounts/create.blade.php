<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Konto erstellen</h2>
    </x-slot>

    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-10 space-y-4">
            @if (! $currentMonth)
                <div class="border border-amber-200 bg-amber-50 text-amber-800 p-3 text-sm accent-box">
                    Kein aktueller Monat vorhanden. Ein Startsaldo kann erst gesetzt werden, wenn ein Monat existiert.
                </div>
            @endif

            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box">
                <div class="p-6">
                    <form method="POST" action="{{ route('accounts.store') }}" class="space-y-4" x-data="{ kind: '{{ old('type', 'ist') }}' }">
                        @csrf

                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" type="text" value="{{ old('name') }}" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="type" value="Typ" />
                            <select id="type" name="type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" required x-model="kind">
                                <option value="ist" @selected(old('type') === 'ist')>Ist (Bank/Bar)</option>
                                <option value="forecast" @selected(old('type') === 'forecast')>Erwartet (Offen)</option>
                                <option value="clearing" @selected(old('type') === 'clearing')>Verrechnung</option>
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        </div>

                        <div x-show="kind === 'ist' || kind === 'clearing'" x-cloak>
                            <x-input-label for="initial_balance" value="Startsaldo (CHF)" />
                            <x-text-input
                                id="initial_balance"
                                name="initial_balance"
                                class="mt-1 block w-full text-right tabular-nums"
                                type="number"
                                step="0.01"
                                inputmode="decimal"
                                value="{{ old('initial_balance') }}"
                                @if (! $currentMonth) disabled @endif
                            />
                            <div class="mt-1 text-xs text-gray-500">Der Startsaldo wird beim Erstellen gesetzt und später automatisch berechnet.</div>
                            <x-input-error :messages="$errors->get('initial_balance')" class="mt-2" />
                        </div>

                        <div class="flex justify-end gap-3">
                            <a href="{{ route('accounts.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm">Abbrechen</a>
                            <x-primary-button>Erstellen</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
