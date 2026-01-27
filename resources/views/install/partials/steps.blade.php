@php
    $steps = [
        0 => 'Willkommen',
        1 => 'Datenbank',
        2 => 'App-Konfiguration',
        3 => 'Datenbank einrichten',
        4 => 'Admin-Benutzer',
        5 => 'Fertig',
    ];
@endphp

<ol class="flex flex-wrap gap-2 text-xs text-gray-500 dark:text-slate-400">
    @foreach ($steps as $index => $label)
        <li class="flex items-center gap-2">
            <span class="{{ $step === $index ? 'bg-[var(--accent)] text-white' : 'bg-gray-200 text-gray-700 dark:bg-slate-700/70 dark:text-slate-200' }} inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full px-2 font-semibold">
                {{ $index + 1 }}
            </span>
            <span class="{{ $step === $index ? 'text-gray-900 dark:text-white font-semibold' : '' }}">{{ $label }}</span>
        </li>
    @endforeach
</ol>
