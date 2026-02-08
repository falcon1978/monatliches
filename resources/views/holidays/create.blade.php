<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ferien erfassen</h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10 space-y-4">
            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box">
                <div class="p-6">
                    <form method="POST" action="{{ route('holidays.store') }}" class="space-y-4" x-data="{ mode: '{{ old('living_cost_mode', 'deduct') }}' }">
                        @csrf

                        <div>
                            <div class="flex items-center gap-2">
                                <x-input-label for="name" value="Bezeichnung (optional)" />
                                <x-info-tooltip text="z.B. Sommerferien, Reise, Urlaub" />
                            </div>
                            <x-text-input id="name" name="name" class="mt-1 block w-full" type="text" value="{{ old('name') }}" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <x-input-label for="date_from" value="Von" />
                                    <x-info-tooltip text="Startdatum der Ferien" />
                                </div>
                                <x-text-input id="date_from" name="date_from" class="mt-1 block w-full" type="date" value="{{ old('date_from') }}" required />
                                <x-input-error :messages="$errors->get('date_from')" class="mt-2" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <x-input-label for="date_to" value="Bis" />
                                    <x-info-tooltip text="Enddatum der Ferien" />
                                </div>
                                <x-text-input id="date_to" name="date_to" class="mt-1 block w-full" type="date" value="{{ old('date_to') }}" required />
                                <x-input-error :messages="$errors->get('date_to')" class="mt-2" />
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <x-input-label value="Lebensunterhalt während Ferien" />
                                <x-info-tooltip text="Legt fest, wie der Tages‑Lebensunterhalt in diesem Zeitraum berechnet wird" />
                            </div>
                            <div class="space-y-2">
                                <label class="flex items-start gap-2">
                                    <input type="radio" name="living_cost_mode" value="deduct" x-model="mode" class="mt-1 border-gray-300 text-[var(--accent)] focus:ring-[var(--accent)]" @checked(old('living_cost_mode', 'deduct') === 'deduct') />
                                    <span class="text-sm text-gray-700">Lebensunterhalt für diesen Ferienzeitraum abziehen</span>
                                </label>
                                <label class="flex items-start gap-2">
                                    <input type="radio" name="living_cost_mode" value="keep" x-model="mode" class="mt-1 border-gray-300 text-[var(--accent)] focus:ring-[var(--accent)]" @checked(old('living_cost_mode', 'deduct') === 'keep') />
                                    <span class="text-sm text-gray-700">Lebensunterhalt für diesen Ferienzeitraum so belassen</span>
                                </label>
                                <label class="flex items-start gap-2">
                                    <input type="radio" name="living_cost_mode" value="custom" x-model="mode" class="mt-1 border-gray-300 text-[var(--accent)] focus:ring-[var(--accent)]" @checked(old('living_cost_mode', 'deduct') === 'custom') />
                                    <span class="text-sm text-gray-700">Benutzerdefinierten Lebensunterhalt für diesen Ferienzeitraum erfassen</span>
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('living_cost_mode')" class="mt-2" />

                            <div x-show="mode === 'custom'" x-cloak class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <x-input-label for="custom_living_cost" value="Benutzerdefinierter Betrag pro Tag (CHF)" />
                                    <x-info-tooltip text="Nur für den gewählten Ferienzeitraum" />
                                </div>
                                <x-text-input id="custom_living_cost" name="custom_living_cost" class="mt-1 block w-full" type="number" step="0.01" value="{{ old('custom_living_cost') }}" />
                                <x-input-error :messages="$errors->get('custom_living_cost')" class="mt-2" />
                                <p class="text-xs text-gray-500">Der Tagesbetrag wird im Monat als eigene Zeile unter dem Lebensunterhalt eingerechnet.</p>
                            </div>

                            <p class="text-xs text-gray-500">Arbeitstage werden bei Selbstständigen automatisch um Ferientage reduziert.</p>
                        </div>

                        <div class="flex justify-end gap-3">
                            <a href="{{ route('holidays.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm">Abbrechen</a>
                            <x-primary-button>Erfassen</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
