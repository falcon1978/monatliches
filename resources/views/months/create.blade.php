<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Monat erstellen</h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box">
                <div class="p-6 space-y-6">
                    <form method="POST" action="{{ route('months.store') }}" class="space-y-4">
                        @csrf

                        <div>
                            <div class="flex items-center gap-2">
                                <x-input-label for="name" value="Name" />
                                <x-info-tooltip text="z.B. März 2026" />
                            </div>
                            <x-text-input id="name" name="name" class="mt-1 block w-full" type="text" value="{{ old('name') }}" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <x-input-label for="date_from" value="Von" />
                                    <x-info-tooltip text="Startdatum des Monats" />
                                </div>
                                <x-text-input id="date_from" name="date_from" class="mt-1 block w-full" type="date" value="{{ old('date_from') }}" required />
                                <x-input-error :messages="$errors->get('date_from')" class="mt-2" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <x-input-label for="date_to" value="Bis" />
                                    <x-info-tooltip text="Enddatum des Monats" />
                                </div>
                                <x-text-input id="date_to" name="date_to" class="mt-1 block w-full" type="date" value="{{ old('date_to') }}" required />
                                <x-input-error :messages="$errors->get('date_to')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center gap-2">
                                <x-input-label for="daily_living_cost" value="Lebensunterhalt pro Tag (CHF)" />
                                <x-info-tooltip text="Tagesbudget für Lebenshaltungskosten im Monat" />
                            </div>
                            <x-text-input id="daily_living_cost" name="daily_living_cost" class="mt-1 block w-full" type="number" step="0.01" value="{{ old('daily_living_cost') }}" required />
                            <x-input-error :messages="$errors->get('daily_living_cost')" class="mt-2" />
                        </div>

                        <div>
                            <div class="flex items-center gap-2">
                                <x-input-label for="source_month_id" value="Optional: Monat kopieren" />
                                <x-info-tooltip text="Einstellungen und Einträge aus einem bestehenden Monat übernehmen" />
                            </div>
                            <select id="source_month_id" name="source_month_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Keine Vorlage</option>
                                @foreach ($months as $month)
                                    <option value="{{ $month->id }}" @selected(old('source_month_id', $sourceMonthId) == $month->id)>
                                        {{ $month->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('source_month_id')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-2">
                            <input id="import_templates" name="import_templates" type="checkbox" value="1" class="rounded border-gray-300 text-[var(--accent)] shadow-sm focus:ring-[var(--accent)]" @checked(old('import_templates', true))>
                            <label for="import_templates" class="text-sm text-gray-700">Wiederkehrende Posten übernehmen</label>
                            <x-info-tooltip text="Kopiert alle Vorlagen direkt in den neuen Monat" />
                        </div>

                        <div class="flex justify-end gap-3">
                            <a href="{{ route('months.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm">Abbrechen</a>
                            <x-primary-button>Erstellen</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
