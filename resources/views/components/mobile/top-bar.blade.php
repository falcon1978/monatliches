@props([
    'title' => 'Monatliches',
    'backHref' => null,
])

@php
    $canAdmin = auth()->user()?->can('viewAny', \App\Models\User::class) ?? false;
    $photoUrl = auth()->user()?->profilePhotoUrl();
    $initials = collect(explode(' ', trim(auth()->user()?->name ?? '')))
        ->filter()
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
    $initials = $initials !== '' ? $initials : '?';
@endphp

<div class="sm:hidden sticky top-0 z-[900]" x-data="{ menuOpen: false }">
    <div class="bg-[var(--surface)]/95 backdrop-blur border-b border-[var(--border)]">
        <div class="flex items-center gap-3 px-4 pb-3 pt-[calc(env(safe-area-inset-top)+0.75rem)]">
            @if ($backHref)
                <a href="{{ $backHref }}" class="touch-target inline-flex items-center justify-center rounded-full border border-[var(--border)] bg-white/80 dark:bg-slate-900/80 text-gray-700 dark:text-slate-200" aria-label="Zurueck">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 18l-6-6 6-6" />
                    </svg>
                </a>
            @endif
            <div class="flex-1 min-w-0">
                <div class="text-lg font-semibold text-gray-900 dark:text-slate-100 truncate">{{ $title }}</div>
            </div>
            @if (trim($slot))
                <div class="flex items-center gap-2">
                    {{ $slot }}
                </div>
            @endif
            <button type="button" class="touch-target h-11 w-11 inline-flex items-center justify-center rounded-full border border-[var(--border)] bg-white/80 dark:bg-slate-900/80 shadow-sm overflow-hidden shrink-0" aria-label="Profil" @click="menuOpen = true">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Profilbild" class="h-full w-full object-cover block" />
                @else
                    <span class="h-full w-full bg-[var(--accent)]/20 flex items-center justify-center text-[11px] font-semibold text-[var(--accent)]">{{ $initials }}</span>
                @endif
            </button>
        </div>
    </div>

    <x-bottom-sheet show="menuOpen" close="menuOpen = false" title="Profil">
        <div class="grid grid-cols-1 gap-3">
            <a href="{{ route('profile.edit') }}" class="touch-target inline-flex items-center justify-between rounded-2xl border border-[var(--border)] bg-white/80 px-4 py-3 text-base font-semibold text-gray-700 dark:text-slate-100">
                <span class="flex items-center gap-3">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="8" r="4" />
                        <path d="M4 21a8 8 0 0 1 16 0" />
                    </svg>
                    Profil
                </span>
                <span aria-hidden="true">→</span>
            </a>
            @if ($canAdmin)
                <a href="{{ route('admin.users.index') }}" class="touch-target inline-flex items-center justify-between rounded-2xl border border-[var(--border)] bg-white/80 px-4 py-3 text-base font-semibold text-gray-700 dark:text-slate-100">
                    <span class="flex items-center gap-3">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z" />
                        </svg>
                        Admin
                    </span>
                    <span aria-hidden="true">→</span>
                </a>
            @endif
        </div>
    </x-bottom-sheet>
</div>
