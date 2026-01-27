<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Einträge: {{ $month->name }}</h2>
                <p class="text-sm text-gray-500">{{ $month->date_from->format('d.m.Y') }} – {{ $month->date_to->format('d.m.Y') }}</p>
            </div>
            <a href="{{ route('months.show', $month) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm">Zur Monatsansicht</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10 space-y-6">
            @include('months.entries.panel', [
                'month' => $month,
                'entries' => $entries,
                'accounts' => $accounts,
                'filters' => $filters ?? [],
                'embedded' => false,
            ])
        </div>
    </div>
</x-app-layout>
