<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Wiederkehrender Posten</h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box">
                <div class="p-6">
                    @php
                        $monthOptions = [
                            1 => 'Jan', 2 => 'Feb', 3 => 'Mär', 4 => 'Apr', 5 => 'Mai', 6 => 'Jun',
                            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dez',
                        ];
                        $selectedMonths = old('months', []);
                        if (! is_array($selectedMonths)) {
                            $selectedMonths = array_filter(array_map('intval', explode(',', (string) $selectedMonths)));
                        }
                    @endphp
                    <form
                        method="POST"
                        action="{{ route('recurring-templates.store') }}"
                        class="space-y-4"
                        x-data="{
                            kind: '{{ old('kind') }}',
                            months: @js($selectedMonths).map(String),
                            allMonths: {{ count($selectedMonths) === 12 ? 'true' : 'false' }},
                            toggleAll() {
                                this.months = this.allMonths ? ['1','2','3','4','5','6','7','8','9','10','11','12'] : [];
                            }
                        }"
                        x-effect="allMonths = months.length === 12"
                    >
                        @csrf

                        <div>
                            <x-input-label for="kind" value="Typ" />
                            <select id="kind" name="kind" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" x-model="kind" required>
                                <option value="">Bitte wählen</option>
                                <option value="income">Wiederkehrende Einnahme</option>
                                <option value="fixcost">Fixkosten</option>
                            </select>
                        </div>

                        <div x-show="kind" x-cloak class="space-y-4">
                            <div>
                                <x-input-label for="name" value="Name" />
                                <x-text-input id="name" name="name" class="mt-1 block w-full" type="text" value="{{ old('name') }}" required />
                            </div>

                            <div>
                                <x-input-label for="amount" value="Betrag" />
                                <x-text-input id="amount" name="amount" class="mt-1 block w-full" type="number" step="0.01" value="{{ old('amount') }}" required />
                            </div>

                            <div x-show="kind === 'fixcost'" x-cloak>
                                <x-input-label for="remaining_amount" value="Restbetrag (optional)" />
                                <x-text-input id="remaining_amount" name="remaining_amount" class="mt-1 block w-full" type="number" step="0.01" value="{{ old('remaining_amount') }}" />
                            </div>

                            <div x-show="kind === 'fixcost'" x-cloak>
                                <x-input-label for="ends_on" value="Enddatum (optional)" />
                                <x-text-input id="ends_on" name="ends_on" class="mt-1 block w-full" type="date" value="{{ old('ends_on') }}" />
                            </div>

                            <div>
                                <x-input-label value="Frequenz (Monate)" />
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <label class="cursor-pointer">
                                        <input type="checkbox" class="peer sr-only" x-model="allMonths" @change="toggleAll()">
                                        <span class="inline-flex items-center rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 peer-checked:border-[var(--accent)] peer-checked:bg-[var(--accent)] peer-checked:text-white">
                                            Alle
                                        </span>
                                    </label>
                                    @foreach ($monthOptions as $number => $label)
                                        <label class="cursor-pointer">
                                            <input type="checkbox" name="months[]" value="{{ $number }}" class="peer sr-only" x-model="months">
                                            <span class="inline-flex items-center rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 peer-checked:border-[var(--accent)] peer-checked:bg-[var(--accent)] peer-checked:text-white">
                                                {{ $label }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('months')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex justify-end gap-3">
                            <a href="{{ route('recurring-templates.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm">Abbrechen</a>
                            <x-primary-button>Speichern</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
