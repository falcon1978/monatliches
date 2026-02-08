@props([
    'show',
    'close',
    'title' => null,
])

<template x-teleport="body">
    <div
        x-show="{{ $show }}"
        x-cloak
        x-transition.opacity
        x-init="$watch(() => {{ $show }}, value => { if (value) { $nextTick(() => { const el = $el.querySelector('[data-autofocus]'); if (el) { el.focus(); } }); } })"
        @keydown.escape.window="{{ $close }}"
        class="fixed inset-x-0 top-0 z-[1000]"
        style="bottom: var(--mobile-nav-offset, 0px);"
    >
        <div class="absolute inset-0 bg-black/40" @click="{{ $close }}"></div>
        <div class="absolute inset-x-0 bottom-0">
            <div class="mx-auto w-full max-w-lg rounded-t-3xl border border-[var(--border)] bg-[var(--surface)] shadow-2xl pb-[env(safe-area-inset-bottom)]">
                <div class="px-5 pt-4">
                    <div class="mx-auto h-1.5 w-10 rounded-full bg-gray-300/70 dark:bg-slate-700"></div>
                    <div class="mt-4 flex items-center justify-between gap-3">
                        @if ($title)
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">{{ $title }}</h3>
                        @endif
                        <button type="button" class="touch-target inline-flex items-center justify-center rounded-full border border-[var(--border)] text-gray-600 dark:text-slate-200" @click="{{ $close }}" aria-label="Schliessen">
                            X
                        </button>
                    </div>
                </div>
                <div class="px-5 pb-6 pt-4">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</template>
