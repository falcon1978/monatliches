<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Monat bearbeiten</h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box">
                <div class="p-6 space-y-6">
                    <form method="POST" action="{{ route('months.update', $month) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" type="text" value="{{ old('name', $month->name) }}" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="date_from" value="Von" />
                                <x-text-input id="date_from" name="date_from" class="mt-1 block w-full" type="date" value="{{ old('date_from', $month->date_from->format('Y-m-d')) }}" required />
                                <x-input-error :messages="$errors->get('date_from')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="date_to" value="Bis" />
                                <x-text-input id="date_to" name="date_to" class="mt-1 block w-full" type="date" value="{{ old('date_to', $month->date_to->format('Y-m-d')) }}" required />
                                <x-input-error :messages="$errors->get('date_to')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="daily_living_cost" value="Lebensunterhalt pro Tag (CHF)" />
                            <x-text-input id="daily_living_cost" name="daily_living_cost" class="mt-1 block w-full" type="number" step="0.01" value="{{ old('daily_living_cost', $month->daily_living_cost) }}" required />
                            <x-input-error :messages="$errors->get('daily_living_cost')" class="mt-2" />
                        </div>

                        <div class="flex justify-end gap-3">
                            <a href="{{ route('months.show', $month) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm">Abbrechen</a>
                            <x-primary-button>Speichern</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
