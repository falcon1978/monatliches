<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Wiederkehrender Posten bearbeiten</h2>
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
                        $currentKind = $template->kind === 'expense' ? 'fixcost' : $template->kind;
                        $templateMonths = $template->parsedMonthsMask();
                        if ($template->frequency === 'monthly') {
                            $templateMonths = range(1, 12);
                        } elseif ($template->frequency === 'quarterly') {
                            $templateMonths = $templateMonths ?: [1, 4, 7, 10];
                        } elseif ($template->frequency === 'yearly') {
                            $templateMonths = $templateMonths ?: [($template->created_at?->month ?? 1)];
                        }
                        $templateMonths = array_values(array_unique(array_map('intval', $templateMonths)));
                        sort($templateMonths);
                        $selectedMonths = old('months', $templateMonths);
                        if (! is_array($selectedMonths)) {
                            $selectedMonths = array_filter(array_map('intval', explode(',', (string) $selectedMonths)));
                        }
                    @endphp
                    <form
                        method="POST"
                        action="{{ route('recurring-templates.update', $template) }}"
                        class="space-y-4"
                        x-data="{
                            kind: '{{ old('kind', $currentKind) }}',
                            months: @js($selectedMonths).map(String),
                            allMonths: {{ count($selectedMonths) === 12 ? 'true' : 'false' }},
                            toggleAll() {
                                this.months = this.allMonths ? ['1','2','3','4','5','6','7','8','9','10','11','12'] : [];
                            }
                        }"
                        x-effect="allMonths = months.length === 12"
                    >
                        @csrf
                        @method('PUT')

                        <div>
                            <div class="flex items-center gap-2">
                                <x-input-label for="kind" value="Typ" />
                                <x-info-tooltip text="Einnahme oder Fixkosten? Wird als Vorlage in neue Monate übernommen." />
                            </div>
                            <select id="kind" name="kind" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" x-model="kind" required>
                                <option value="">Bitte wählen</option>
                                <option value="income" @selected(old('kind', $currentKind) === 'income')>Wiederkehrende Einnahme</option>
                                <option value="fixcost" @selected(old('kind', $currentKind) === 'fixcost')>Fixkosten</option>
                            </select>
                        </div>

                        <div x-show="kind" x-cloak class="space-y-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <x-input-label for="name" value="Name" />
                                    <x-info-tooltip text="z.B. Miete, Lohn, Versicherung" />
                                </div>
                                <x-text-input id="name" name="name" class="mt-1 block w-full" type="text" value="{{ old('name', $template->name) }}" required />
                            </div>

                            <div>
                                <div class="flex items-center gap-2">
                                    <x-input-label for="amount" value="Betrag" />
                                    <x-info-tooltip text="CHF‑Betrag der Vorlage pro Monat" />
                                </div>
                                <x-text-input id="amount" name="amount" class="mt-1 block w-full" type="number" step="0.01" value="{{ old('amount', $template->amount) }}" required />
                            </div>

                            <div x-show="kind === 'fixcost'" x-cloak>
                                <div class="flex items-center gap-2">
                                    <x-input-label for="remaining_amount" value="Restbetrag (optional)" />
                                    <x-info-tooltip text="Praktisch für Raten oder begrenzte Fixkosten" />
                                </div>
                                <x-text-input id="remaining_amount" name="remaining_amount" class="mt-1 block w-full" type="number" step="0.01" value="{{ old('remaining_amount', $template->remaining_amount) }}" />
                            </div>

                            <div x-show="kind === 'fixcost'" x-cloak>
                                <div class="flex items-center gap-2">
                                    <x-input-label for="ends_on" value="Enddatum (optional)" />
                                    <x-info-tooltip text="Ab diesem Datum wird die Vorlage nicht mehr übernommen" />
                                </div>
                                <x-text-input id="ends_on" name="ends_on" class="mt-1 block w-full" type="date" value="{{ old('ends_on', $template->ends_on?->format('Y-m-d')) }}" />
                            </div>

                            <div>
                                <div class="flex items-center gap-2">
                                    <x-input-label value="Frequenz (Monate)" />
                                    <x-info-tooltip text="Legt fest, in welchen Monaten die Vorlage auftaucht" />
                                </div>
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

                    <form method="POST" action="{{ route('recurring-templates.destroy', $template) }}" class="mt-6">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Vorlage löschen</x-danger-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
