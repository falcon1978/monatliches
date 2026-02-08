@php
    $isDashboard = request()->routeIs('dashboard') || request()->routeIs('months.index');
    $isTemplates = request()->routeIs('recurring-templates.*');
    $isAccounts = request()->routeIs('accounts.*');
    $isHolidays = request()->routeIs('holidays.*');
    $newHref = route('months.create');
    $newAria = 'Neu';
    $newQuickAdd = false;
    $canAdmin = auth()->user()?->can('viewAny', \App\Models\User::class) ?? false;
    $monthsNav = collect();
    $currentMonthId = null;
    if (auth()->check()) {
        $monthsNav = \App\Models\Month::query()
            ->where('user_id', auth()->id())
            ->whereNull('archived_at')
            ->orderBy('date_from')
            ->get();
    }
    if (request()->routeIs('months.show')) {
        $monthParam = request()->route('month');
        $currentMonthId = is_object($monthParam) ? $monthParam->id : $monthParam;
    }
    $photoUrl = auth()->user()?->profilePhotoUrl();
    $initials = collect(explode(' ', trim(auth()->user()?->name ?? '')))
        ->filter()
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
    $initials = $initials !== '' ? $initials : '?';

    if (request()->routeIs('months.show')) {
        $monthParam = request()->route('month');
        if ($monthParam) {
            $newHref = route('months.show', $monthParam) . '?quick_add=1';
        }
        $newAria = 'Neue Einnahme oder Rechnung';
        $newQuickAdd = true;
    } elseif (request()->routeIs('holidays.*')) {
        $newHref = route('holidays.create');
        $newAria = 'Neue Ferien';
    } elseif (request()->routeIs('accounts.*')) {
        $newHref = route('accounts.create');
        $newAria = 'Neues Konto';
    } elseif (request()->routeIs('recurring-templates.*')) {
        $newHref = route('recurring-templates.create');
        $newAria = 'Neuer wiederkehrender Posten';
    } elseif (request()->routeIs('months.index') || request()->routeIs('dashboard')) {
        $newHref = route('months.create');
        $newAria = 'Neuer Monat';
    }
@endphp

<nav
    class="sm:hidden fixed inset-x-0 bottom-0 z-[950]"
    x-data="{
        onMonthShow: {{ request()->routeIs('months.show') ? 'true' : 'false' }},
        menuOpen: false,
        homeOpen: false,
        syncNavOffset() {
            const bar = document.querySelector('[data-mobile-nav-bar]');
            const height = bar ? bar.getBoundingClientRect().height : 0;
            document.documentElement.style.setProperty('--mobile-nav-offset', `${Math.ceil(height)}px`);
        }
    }"
    x-init="syncNavOffset(); $nextTick(() => syncNavOffset()); window.addEventListener('resize', () => syncNavOffset());"
>
    <div data-mobile-nav-bar class="bg-[var(--surface)] border-t border-[var(--border)] px-3 pt-2 pb-[calc(env(safe-area-inset-bottom)+0.75rem)]">
        <div class="grid grid-cols-6 gap-2">
            <button type="button" class="touch-target h-11 w-11 inline-flex items-center justify-center rounded-full border border-[var(--border)] bg-white/80 dark:bg-slate-900/80 shadow-sm overflow-hidden" aria-label="Profil" @click="menuOpen = true">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Profilbild" class="h-full w-full object-cover block" />
                @else
                    <span class="h-full w-full bg-[var(--accent)]/20 flex items-center justify-center text-[11px] font-semibold text-[var(--accent)]">{{ $initials }}</span>
                @endif
            </button>

            <button type="button" class="touch-target flex items-center justify-center rounded-2xl {{ $isDashboard ? 'text-[var(--accent)] bg-[var(--accent)]/10' : 'text-gray-600 dark:text-slate-300' }}" aria-label="Monatsübersicht" @click="homeOpen = true">
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="18" rx="2" />
                    <path d="M16 2v4M8 2v4M3 10h18" />
                </svg>
                <span class="sr-only">Monatsübersicht</span>
            </button>

            <a href="{{ route('recurring-templates.index') }}" class="touch-target flex items-center justify-center rounded-2xl {{ $isTemplates ? 'text-[var(--accent)] bg-[var(--accent)]/10' : 'text-gray-600 dark:text-slate-300' }}" aria-label="Wiederkehrende">
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 2l4 4-4 4" />
                    <path d="M3 11V9a4 4 0 0 1 4-4h14" />
                    <path d="M7 22l-4-4 4-4" />
                    <path d="M21 13v2a4 4 0 0 1-4 4H3" />
                </svg>
                <span class="sr-only">Wiederkehrende</span>
            </a>

            <a href="{{ route('accounts.index') }}" class="touch-target flex items-center justify-center rounded-2xl {{ $isAccounts ? 'text-[var(--accent)] bg-[var(--accent)]/10' : 'text-gray-600 dark:text-slate-300' }}" aria-label="Konten">
                <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 9l9-5 9 5" />
                    <path d="M4 9h16" />
                    <path d="M5 9v9M9 9v9M15 9v9M19 9v9" />
                    <path d="M3 18h18" />
                </svg>
                <span class="sr-only">Konten</span>
            </a>

            <a href="{{ route('holidays.index') }}" class="touch-target flex items-center justify-center rounded-2xl {{ $isHolidays ? 'text-[var(--accent)] bg-[var(--accent)]/10' : 'text-gray-600 dark:text-slate-300' }}" aria-label="Ferien">
                <svg viewBox="0 0 512 512" class="h-7 w-7" fill="currentColor" aria-hidden="true">
                    <path d="M350.9,364.4c-3.2-0.3-6.4-0.5-9.7-0.5c-15.1,0-29.5,3.4-42.3,9.4c-16.1-19-36.9-33.9-60.8-42.7c-4-1.5-8.2-2.8-12.4-3.9c-12.1-3.3-24.7-5-37.8-5c-17,0-33.2,2.9-48.3,8.3c-55.4,19.6-95.4,71.9-96.7,133.8H441C441,411.9,401.5,369.3,350.9,364.4z M153.8,370.3c-28.2,10-50.6,31.9-61.5,60.1c-1.1,2.8-3.7,4.5-6.5,4.5c-0.8,0-1.7-0.1-2.5-0.5c-3.6-1.4-5.4-5.4-4-9c12.3-32.1,37.8-56.9,69.9-68.3c3.7-1.3,7.6,0.6,8.9,4.3C159.4,365.1,157.5,369,153.8,370.3z" />
                    <path d="M322.9,235.9c15.3,0,27.2,5.8,32.4,8.8c3.9-20.7,8.8-39.8,13.3-55.6l-30.4-12.3l-0.2-0.8c-50.5,41.2-79.2,95.5-95.5,141.3c0.1,0,0.3,0.1,0.4,0.2c22.5,8.3,43,21.7,59.7,39.1c12.3-4.5,25.2-6.7,38.5-6.7c2.5,0,5.1,0.1,7.6,0.3c-1.1-10.3-1.6-20.9-1.5-31.6c-0.7,0.2-1.4,0.3-2,0.3c-1.1,0-2.2-0.3-3.3-0.8c-0.3-0.2-21.5-11.3-41.5-11.3c-3.9,0-7-3.1-7-7c0-3.9,3.1-7,7-7c21.4,0,42.6,10.2,47.2,12.5c0.8-15.7,2.6-31.3,5-46.3c-1-0.2-2-0.6-2.9-1.2c-0.2-0.2-11.5-7.9-27-7.9c-3.9,0-7-3.1-7-7C315.9,239,319.1,235.9,322.9,235.9z" />
                    <path d="M413.4,95.4c0,0-22.8-57.2-94.8-45.6c0,0,30.5,17.5,27.4,42.5c0,0-48.5-26.7-98.7,30.2c0,0,92.3-4.9,103,44.1l22.3,9c0,0,61.7,7.8,63.8,58.8c0,0,27.9-31.1,15.8-69.5c0,0,24.8-1.6,32.1,20C484.4,185,505.8,92.3,413.4,95.4z" />
                    <path d="M171.9,233.6c-6.8-7.6-14.3-14.9-22.6-21.8l-0.2,0.7l-26.4,10.7c6.9,24.5,14.6,58.3,16.4,92.1c15.7-5,32.1-7.6,48.7-7.6c10.7,0,21.4,1.1,31.8,3.2c-2-5-4.3-10-6.7-15.1c-1.1,0.5-2.3,0.8-3.6,0.7c-0.3,0-26.8-1.8-39.1-0.1c-0.3,0-0.6,0.1-1,0.1c-3.4,0-6.4-2.5-6.9-6c-0.5-3.8,2.1-7.4,6-7.9c10.6-1.5,29.3-0.7,37.6-0.3c-6.8-12.6-15-25.2-24.6-37.3c-0.8,0.7-1.8,1.2-2.8,1.4c-7.9,1.9-16.3,4.7-18.1,6.1c-1.4,1.3-3.1,2-4.9,2c-1.8,0-3.6-0.7-4.9-2.1c-2.7-2.7-2.7-7.2,0-9.9C154.6,238.7,164.6,235.5,171.9,233.6z" />
                    <path d="M53.6,201c-9.9,31.5,13,57,13,57c1.7-41.9,52.3-48.2,52.3-48.2l18.3-7.4c8.7-40.2,84.4-36.2,84.4-36.2c-41.2-46.6-80.9-24.8-80.9-24.8c-2.6-20.4,22.5-34.8,22.5-34.8c-59-9.5-77.7,37.4-77.7,37.4c-75.7-2.6-58.1,73.4-58.1,73.4C33.3,199.7,53.6,201,53.6,201z" />
                </svg>
                <span class="sr-only">Ferien</span>
            </a>

            <a href="{{ $newHref }}" class="touch-target flex items-center justify-center rounded-2xl bg-[var(--accent)] text-white shadow-sm" aria-label="{{ $newAria }}" @if ($newQuickAdd) @click.prevent="if (onMonthShow) { $dispatch('open-quick-add'); } else { window.location = '{{ $newHref }}'; }" @endif>
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 5v14" />
                    <path d="M5 12h14" />
                </svg>
                <span class="sr-only">Neu</span>
            </a>
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
                <a href="{{ route('admin.update.show') }}" class="touch-target inline-flex items-center justify-between rounded-2xl border border-[var(--border)] bg-white/80 px-4 py-3 text-base font-semibold text-gray-700 dark:text-slate-100">
                    <span class="flex items-center gap-3">
                        <x-icon-receive class="h-5 w-5" />
                        Update
                    </span>
                    <span aria-hidden="true">→</span>
                </a>
            @endif
        </div>
    </x-bottom-sheet>

    <x-bottom-sheet show="homeOpen" close="homeOpen = false" title="Monat wählen">
        <div class="flex h-[calc(100vh-12rem)] max-h-[calc(100vh-12rem)] flex-col">
            <div class="space-y-3 shrink-0">
                <div class="text-xs uppercase tracking-[0.2em] text-gray-500">Navigation</div>
                <button
                    type="button"
                    class="touch-target w-full rounded-2xl border border-[var(--border)] bg-white/80 px-4 py-3 text-left text-base font-semibold text-gray-700 dark:bg-slate-900/80 dark:text-slate-100"
                    @click="homeOpen = false; window.location = '{{ route('dashboard') }}';"
                >
                    Übersicht
                </button>
                @if ($monthsNav->isNotEmpty())
                    <div class="text-[10px] uppercase tracking-[0.25em] text-gray-500">Monate</div>
                @endif
            </div>

            @if ($monthsNav->isNotEmpty())
                <div class="mt-3 flex-1 overflow-y-auto pr-1">
                    <div class="space-y-1.5">
                        @foreach ($monthsNav as $monthNav)
                            @php
                                $isCurrent = $currentMonthId && (int) $currentMonthId === (int) $monthNav->id;
                            @endphp
                            <button
                                type="button"
                                class="touch-target w-full rounded-xl border border-[var(--border)] px-3 py-2 text-left {{ $isCurrent ? 'bg-[var(--accent)]/10 text-[var(--accent)]' : 'bg-white/80 text-gray-700 dark:bg-slate-900/80 dark:text-slate-100' }}"
                                @click="homeOpen = false; window.location = '{{ route('months.show', $monthNav) }}';"
                            >
                                <div class="flex items-center justify-between gap-2 text-sm">
                                    <span class="font-semibold">{{ $monthNav->name }}</span>
                                    <span class="text-[10px] text-gray-500">{{ $monthNav->date_from->format('d.m.Y') }} – {{ $monthNav->date_to->format('d.m.Y') }}</span>
                                    @if ($isCurrent)
                                        <x-icon-check class="h-4 w-4" />
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-bottom-sheet>
</nav>
