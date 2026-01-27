<div class="relative z-[900] border-b accent-box bg-white/80 dark:bg-slate-900/80 backdrop-blur">
    <div class="w-full px-4 sm:px-6 lg:px-10 py-2">
        <div class="flex items-center gap-3">
            <a href="{{ route('months.index') }}" class="inline-flex items-center gap-2">
                <x-application-logo class="h-14 w-14" />
                <span class="text-[10px] uppercase tracking-[0.3em] text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-100">Übersicht</span>
            </a>
            <div class="flex-1 overflow-x-auto">
                <div class="flex items-center gap-2 whitespace-nowrap py-1">
                    @foreach ($monthBand['months'] as $bandMonth)
                        @php
                            $isPrimary = ($monthBand['currentMonthId'] ?? null) === $bandMonth->id;
                            $chipClass = $isPrimary
                                ? 'bg-[var(--accent)] text-white shadow-sm ring-1 ring-[var(--accent)]'
                                : 'bg-white/70 dark:bg-slate-900/70 text-gray-700 dark:text-slate-200 ring-1 ring-black/5 dark:ring-white/10 hover:text-gray-900 dark:hover:text-slate-100 hover:ring-black/10';

                            $title = $bandMonth->date_from->format('d.m.Y') . ' - ' . $bandMonth->date_to->format('d.m.Y');
                        @endphp

                        <a
                            href="{{ route('months.show', $bandMonth) }}"
                            class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold transition {{ $chipClass }}"
                            title="{{ $title }}"
                            @if ($isPrimary) aria-current="true" @endif
                        >
                            <span>{{ $bandMonth->name }}</span>
                            @if ($isPrimary)
                                <span class="h-1.5 w-1.5 rounded-full bg-white/90"></span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="shrink-0 flex items-center gap-2">
                <button type="button" data-theme-toggle class="theme-toggle inline-flex h-9 w-9 items-center justify-center rounded-full border border-[var(--accent)] bg-white/80 dark:bg-slate-900/80 text-[var(--accent)] shadow-sm transition hover:opacity-80" aria-label="Dunkelmodus">
                    <span class="theme-icon-moon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
                        </svg>
                    </span>
                    <span class="theme-icon-sun" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="4" />
                            <path d="M12 2v2" />
                            <path d="M12 20v2" />
                            <path d="M4.93 4.93l1.41 1.41" />
                            <path d="M17.66 17.66l1.41 1.41" />
                            <path d="M2 12h2" />
                            <path d="M20 12h2" />
                            <path d="M4.93 19.07l1.41-1.41" />
                            <path d="M17.66 6.34l1.41-1.41" />
                        </svg>
                    </span>
                </button>
                <x-dropdown align="right" width="48" content-classes="py-1 bg-white/95 dark:bg-slate-900/95 backdrop-blur">
                    <x-slot name="trigger">
                        <button class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[var(--accent)] bg-white/80 dark:bg-slate-900/80 text-[var(--accent)] shadow-sm transition hover:opacity-80" type="button" aria-label="Einstellungen">
                            <x-icon-settings class="h-5 w-5" />
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('accounts.index')">Konten</x-dropdown-link>
                        <x-dropdown-link :href="route('recurring-templates.index')">Wiederkehrende Posten</x-dropdown-link>
                        @can('viewAny', App\Models\User::class)
                            <x-dropdown-link :href="route('admin.users.index')">Admin</x-dropdown-link>
                        @endcan
                        <div class="my-1 border-t border-gray-200/70"></div>
                        <x-dropdown-link :href="route('profile.edit')">Profil</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link href="#" onclick="event.preventDefault(); this.closest('form').submit();">Abmelden</x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
</div>
