<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Benutzerverwaltung</h2>
            <a href="{{ route('admin.users.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-md text-sm">User erstellen</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg p-6 border accent-box">
                <div class="overflow-x-auto">
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
                                        <a href="{{ route('admin.users.edit', $user) }}" class="text-sm text-[var(--accent)] underline">Bearbeiten</a>
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
