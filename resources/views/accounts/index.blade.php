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
        $fmt = fn ($value) => new \Illuminate\Support\HtmlString(
            number_format((float) $value, 2, '.', "'")
        );
        $hasClearing = $accounts->contains(fn ($account) => $account->type === 'clearing');
        $typeLabels = [
            'ist' => 'Ist',
            'forecast' => 'Forecast',
            'clearing' => 'Verrechnung',
        ];
    @endphp

    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-10 space-y-4">
            @if (session('status'))
                <div class="border border-green-200 dark:border-emerald-700/60 bg-green-50 dark:bg-emerald-900/30 text-green-800 dark:text-emerald-100 p-3 text-sm accent-box" x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition.opacity x-cloak>
                    {{ session('status') }}
                </div>
            @endif

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
            @if (! $currentMonth)
                <div class="border border-amber-200 bg-amber-50 text-amber-800 p-3 text-sm accent-box">
                    Kein aktueller Monat vorhanden. Kontostände können erst gesetzt werden, wenn ein Monat existiert.
                </div>
            @endif

            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg overflow-hidden border accent-box">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-2">Name</th>
                            <th class="px-4 py-2">Typ</th>
                            <th class="px-4 py-2 text-right">Kontostand</th>
                            <th class="px-4 py-2 text-right">Saldo setzen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($accounts as $account)
                            @php
                                $forecastOpen = (float) ($forecastBalances[$account->id] ?? 0);
                                $balance = $account->type === 'forecast'
                                    ? $forecastOpen
                                    : (float) ($accountBalances[$account->id] ?? 0);
                                $meta = $balanceMeta[$account->id] ?? null;
                                $isRelevant = $account->type === 'forecast'
                                    ? ((float) $forecastOpen !== 0.0)
                                    : ($meta['is_relevant'] ?? ((float) $balance !== 0.0));
                                $balanceClass = $balance < 0 ? 'text-red-700' : 'text-gray-900';
                                $balanceClass = $isRelevant ? $balanceClass : 'text-gray-400';
                                $balanceInput = number_format($balance, 2, '.', '');
                                $canEditBalance = $currentMonth && in_array($account->type, ['ist', 'clearing'], true);
                            @endphp
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $account->name }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $typeLabels[$account->type] ?? $account->type }}</td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold {{ $balanceClass }}">
                                    CHF {{ $fmt($balance) }}
                                    @if (! $isRelevant)
                                        <span class="ml-2 text-[10px] uppercase tracking-wide text-gray-400">nicht relevant</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <div class="flex flex-col items-end gap-2">
                                        @if ($canEditBalance)
                                            <form method="POST" action="{{ route('months.balances.update', [$currentMonth, $account]) }}" class="flex items-center gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <input type="number" step="0.01" name="amount" value="{{ $balanceInput }}" class="w-28 border border-gray-300 rounded px-2 py-1 text-sm text-right tabular-nums focus:border-[var(--accent)] focus:ring-[var(--accent)]" required>
                                                <button type="submit" class="px-2 py-1 bg-[var(--accent)] text-white rounded text-xs">Setzen</button>
                                            </form>
                                        @else
                                            <div class="text-xs text-gray-500">–</div>
                                        @endif
                                        <a href="{{ route('accounts.edit', $account) }}" class="text-sm text-[var(--accent)] underline">Bearbeiten</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-gray-500">Noch keine Konten vorhanden.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="text-xs text-gray-500">
                @if ($hasClearing)
                    Forecast-Konten zeigen offene Einnahmen; Ist-/Verrechnungs-Konten zeigen den Kontostand des aktuellen Monats.
                @else
                    Forecast-Konten zeigen offene Einnahmen; Ist-Konten zeigen den Kontostand des aktuellen Monats.
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
