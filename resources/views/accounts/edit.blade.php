<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Konto bearbeiten</h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10 space-y-4">
            @if ($errors->has('account'))
                <div class="border border-red-200 bg-red-50 text-red-800 p-3 text-sm accent-box">
                    {{ $errors->first('account') }}
                </div>
            @endif

            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box">
                <div class="p-6">
                    <form method="POST" action="{{ route('accounts.update', $account) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" type="text" value="{{ old('name', $account->name) }}" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="type" value="Typ" />
                            <select id="type" name="type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                                <option value="ist" @selected(old('type', $account->type) === 'ist')>Ist (Bank/Bar)</option>
                                <option value="forecast" @selected(old('type', $account->type) === 'forecast')>Erwartet (Offen)</option>
                                @if (old('type', $account->type) === 'clearing')
                                    <option value="clearing" selected>Verrechnung</option>
                                @endif
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        </div>

                        <div class="flex justify-end gap-3">
                            <a href="{{ route('accounts.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm">Abbrechen</a>
                            <x-primary-button>Speichern</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box">
                <div class="p-6">
                    <div class="text-sm text-gray-600">Konten mit Einträgen können nicht gelöscht werden.</div>
                    <form method="POST" action="{{ route('accounts.destroy', $account) }}" class="mt-4" onsubmit="return confirm('Konto wirklich löschen?');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Konto löschen</x-danger-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
