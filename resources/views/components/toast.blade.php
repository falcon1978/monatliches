@props([
    'message' => null,
    'variant' => 'success',
])

@php
    $variantClasses = [
        'success' => 'border-emerald-200/70 bg-emerald-50 text-emerald-900 dark:border-emerald-700/60 dark:bg-emerald-900/30 dark:text-emerald-100',
        'error' => 'border-red-200/70 bg-red-50 text-red-900 dark:border-red-700/60 dark:bg-red-900/30 dark:text-red-100',
        'warning' => 'border-amber-200/70 bg-amber-50 text-amber-900 dark:border-amber-700/60 dark:bg-amber-900/30 dark:text-amber-100',
    ];
    $classes = $variantClasses[$variant] ?? $variantClasses['success'];
@endphp

@if ($message)
    <div class="fixed inset-x-0 bottom-[calc(90px+env(safe-area-inset-bottom))] z-[1000] px-4 sm:px-6 lg:px-10">
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition.opacity x-cloak class="mx-auto max-w-lg">
            <div class="rounded-2xl border px-4 py-3 text-sm shadow-sm {{ $classes }}">
                {{ $message }}
            </div>
        </div>
    </div>
@endif
