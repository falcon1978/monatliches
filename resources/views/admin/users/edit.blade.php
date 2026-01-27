<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">User bearbeiten</h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box">
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" type="text" value="{{ old('name', $user->name) }}" required />
                        </div>

                        <div>
                            <x-input-label for="email" value="E-Mail" />
                            <x-text-input id="email" name="email" class="mt-1 block w-full" type="email" value="{{ old('email', $user->email) }}" required />
                        </div>

                        <div>
                            <x-input-label for="password" value="Neues Passwort (optional)" />
                            <x-text-input id="password" name="password" class="mt-1 block w-full" type="password" />
                        </div>

                        <div class="flex items-center gap-2">
                            <input id="is_admin" name="is_admin" type="checkbox" value="1" class="rounded border-gray-300 text-[var(--accent)] shadow-sm focus:ring-[var(--accent)]" @checked(old('is_admin', $user->is_admin))>
                            <label for="is_admin" class="text-sm text-gray-700">Admin</label>
                        </div>

                        <div class="flex justify-end gap-3">
                            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm">Abbrechen</a>
                            <x-primary-button>Speichern</x-primary-button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-6">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>User löschen</x-danger-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
