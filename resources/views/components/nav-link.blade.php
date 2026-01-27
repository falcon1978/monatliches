@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold text-[var(--accent)] bg-white/90 ring-1 ring-[var(--accent)]/30 shadow-sm'
            : 'inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-white/70 ring-1 ring-transparent hover:ring-gray-200 transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
