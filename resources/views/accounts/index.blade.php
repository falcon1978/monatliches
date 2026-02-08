<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Konten</h2>
                @if ($currentMonth)
                    <p class="text-sm text-gray-500">Monat: {{ $currentMonth->name }}</p>
                @endif
            </div>
            <a href="{{ route('accounts.create') }}" class="inline-flex items-center px-3 py-1.5 bg-[var(--accent)] text-white rounded text-sm">Konto erstellen</a>
        </div>
    </x-slot>

    @php
        $typeLabels = [
            'ist' => 'Ist',
            'forecast' => 'Erwartet',
            'clearing' => 'Verrechnung',
        ];
    @endphp

    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-10 space-y-4">
            @if ($errors->has('account'))
                <div class="border border-red-200 bg-red-50 text-red-800 p-3 text-sm accent-box">
                    {{ $errors->first('account') }}
                </div>
            @endif
            @if ($errors->has('balance'))
                <div class="border border-red-200 bg-red-50 text-red-800 p-3 text-sm accent-box">
                    {{ $errors->first('balance') }}
                </div>
            @endif
            <div class="sm:hidden space-y-3">
                @forelse ($accounts as $account)
                    @php
                        $accountBg = match ($account->type) {
                            'forecast' => 'bg-green-50/70 dark:bg-emerald-950/30',
                            'clearing' => 'bg-amber-50/70 dark:bg-amber-950/30',
                            default => 'bg-blue-50/70 dark:bg-blue-950/30',
                        };
                    @endphp
                    <div class="rounded-2xl border border-[var(--border)] {{ $accountBg }} shadow-sm p-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0 flex items-center gap-2">
                                <a href="{{ route('accounts.edit', $account) }}" class="touch-target inline-flex items-center justify-center rounded-full text-gray-400 hover:text-gray-700" aria-label="Bearbeiten">
                                    <x-icon-edit class="h-4 w-4" />
                                </a>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-slate-100 truncate">{{ $account->name }}</div>
                                    <div class="mt-1 text-[10px] uppercase tracking-[0.2em] text-gray-500">{{ $typeLabels[$account->type] ?? $account->type }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-[var(--border)] bg-white/70 dark:bg-slate-900/60 p-4 text-sm text-gray-500">
                        Noch keine Konten vorhanden. Lege zuerst ein Ist‑Konto (Bank/Bar) an.
                    </div>
                @endforelse
            </div>

            <div class="hidden sm:block bg-white dark:bg-slate-900/80 shadow sm:rounded-lg overflow-hidden border accent-box">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-2">Name</th>
                            <th class="px-4 py-2">Typ</th>
                            <th class="px-4 py-2 text-right">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($accounts as $account)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $account->name }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $typeLabels[$account->type] ?? $account->type }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('accounts.edit', $account) }}" class="text-sm text-[var(--accent)] underline">Bearbeiten</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-gray-500">Noch keine Konten vorhanden. Lege zuerst ein Ist‑Konto (Bank/Bar) an.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
