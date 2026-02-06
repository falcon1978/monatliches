<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ferien</h2>
                <p class="text-sm text-gray-500">Ferien beeinflussen Arbeitstage und den Lebensunterhalt.</p>
            </div>
            <a href="{{ route('holidays.create') }}" class="inline-flex items-center px-3 py-1.5 bg-[var(--accent)] text-white rounded text-sm">Ferien erfassen</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-10 space-y-4">
            @if ($holidays->isEmpty())
                <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg p-6 border accent-box">
                    <p class="text-gray-600">Noch keine Ferien erfasst.</p>
                </div>
            @else
                @php
                    $modeLabels = [
                        'deduct' => 'Lebensunterhalt abziehen',
                        'keep' => 'Lebensunterhalt belassen',
                        'custom' => 'Benutzerdefiniert',
                    ];
                @endphp
                <div class="space-y-3">
                    @foreach ($holidays as $holiday)
                        @php
                            $mode = $holiday->living_cost_mode ?? 'deduct';
                            $label = $modeLabels[$mode] ?? $mode;
                            $customLabel = $mode === 'custom' && $holiday->custom_living_cost !== null
                                ? 'CHF '.number_format((float) $holiday->custom_living_cost, 2, '.', "'").'/Tag'
                                : null;
                        @endphp
                        <a href="{{ route('holidays.edit', $holiday) }}" class="group block rounded-lg border accent-box bg-white/80 dark:bg-slate-900/70 px-4 py-3 shadow-sm transition hover:shadow-md">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-slate-100">{{ $holiday->name ?: 'Ferien' }}</div>
                                    <div class="text-xs text-gray-600">{{ $holiday->date_from->format('d.m.Y') }} – {{ $holiday->date_to->format('d.m.Y') }}</div>
                                    <div class="text-xs text-gray-600">
                                        {{ $label }}@if ($customLabel) <span class="text-gray-500">({{ $customLabel }})</span>@endif
                                    </div>
                                </div>
                                <div class="text-[var(--accent)] opacity-70 group-hover:opacity-100" aria-hidden="true">
                                    <x-icon-edit class="h-4 w-4" />
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
