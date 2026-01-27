@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full px-4 py-2 rounded-lg text-sm font-semibold text-[var(--accent)] bg-white ring-1 ring-[var(--accent)]/30 shadow-sm'
            : 'block w-full px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-white/80 ring-1 ring-transparent hover:ring-gray-200 transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
