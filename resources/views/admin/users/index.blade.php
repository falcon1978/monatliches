<x-app-layout :mobile-title="'Benutzerverwaltung'">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Benutzerverwaltung</h2>
            <a href="{{ route('admin.users.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-md text-sm">User erstellen</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            <div class="sm:hidden mb-4">
                <a href="{{ route('admin.users.create') }}" class="touch-target inline-flex items-center justify-center w-full rounded-2xl bg-[var(--accent)] text-white text-base font-semibold">User erstellen</a>
            </div>
            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg p-6 border accent-box">
                <div class="sm:hidden space-y-3">
                    @foreach ($users as $user)
                        <div class="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-lg font-semibold text-gray-900 dark:text-slate-100">{{ $user->name }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $user->email }}</div>
                                    <div class="mt-1 text-xs text-gray-500">Admin: {{ $user->is_admin ? 'Ja' : 'Nein' }}</div>
                                </div>
                                <a href="{{ route('admin.users.edit', $user) }}" class="touch-target inline-flex items-center justify-center rounded-xl border border-[var(--border)] bg-white/80 text-gray-700" aria-label="Bearbeiten">
                                    <x-icon-edit class="h-5 w-5" />
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-2">Name</th>
                                <th>E-Mail</th>
                                <th>Admin</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($users as $user)
                                <tr>
                                    <td class="py-2">{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ $user->is_admin ? 'Ja' : 'Nein' }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center accent-icon hover:opacity-80" aria-label="Bearbeiten">
                                            <x-icon-edit class="h-4 w-4" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
