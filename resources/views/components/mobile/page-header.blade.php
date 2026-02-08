@props([
    'title' => 'Monatliches',
])

<div class="sm:hidden sticky top-0 z-[980]">
    <div class="bg-[var(--surface)] border-b border-[var(--border)] pt-[env(safe-area-inset-top)]">
        <div class="h-[var(--mobile-header-height)] flex items-center px-4">
            <div class="text-sm font-semibold text-gray-900 dark:text-slate-100 truncate">
                {{ $title }}
            </div>
        </div>
    </div>
</div>
