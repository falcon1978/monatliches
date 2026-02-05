@props([
    'title',
    'sum',
    'bgClass' => 'bg-white',
])

<div {{ $attributes->merge(['class' => 'section-table border accent-box rounded-lg overflow-hidden ' . $bgClass]) }}>
    <div class="px-3 py-2 text-sm font-semibold uppercase tracking-wide text-gray-800 dark:text-slate-100 flex items-center justify-between gap-2">
        <div>{{ $title }} CHF <span class="tabular-nums font-semibold">{{ $sum }}</span></div>
        @isset($actions)
            <div class="flex items-center gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>
    <div class="px-[5px] pb-3 overflow-x-auto">
        {{ $slot }}
    </div>
</div>
