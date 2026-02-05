@props([
    'title',
    'sum',
    'bgClass' => 'bg-white',
])

<div {{ $attributes->merge(['class' => 'section-table border-0 sm:border accent-box rounded-none sm:rounded-lg overflow-hidden ' . $bgClass]) }}>
    <style>
        .section-table th:last-child,
        .section-table td:last-child {
            padding-right: 0.25rem;
        }
    </style>
    <div class="px-3 py-2 text-sm font-semibold uppercase tracking-wide text-gray-800 dark:text-slate-100 flex items-center justify-between gap-2">
        <div>{{ $title }} CHF <span class="tabular-nums font-semibold">{{ $sum }}</span></div>
        @isset($actions)
            <div class="flex items-center gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>
    <div class="px-3 pb-3 overflow-x-auto">
        {{ $slot }}
    </div>
</div>
