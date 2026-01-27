<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">User erstellen</h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" type="text" value="{{ old('name') }}" required />
                        </div>

                        <div>
                            <x-input-label for="email" value="E-Mail" />
                            <x-text-input id="email" name="email" class="mt-1 block w-full" type="email" value="{{ old('email') }}" required />
                        </div>

                        <div>
                            <x-input-label for="password" value="Passwort" />
                            <x-text-input id="password" name="password" class="mt-1 block w-full" type="password" required />
                        </div>

                        <div class="flex items-center gap-2">
                            <input id="is_admin" name="is_admin" type="checkbox" value="1" class="rounded border-gray-300 text-[var(--accent)] shadow-sm focus:ring-[var(--accent)]">
                            <label for="is_admin" class="text-sm text-gray-700">Admin</label>
                        </div>

                        <div class="flex justify-end gap-3">
                            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm">Abbrechen</a>
                            <x-primary-button>Erstellen</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
