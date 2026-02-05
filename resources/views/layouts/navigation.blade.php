<nav x-data="{ open: false }" class="relative z-[950] border-b border-gray-200/70 bg-white/80 backdrop-blur dark:border-slate-800/80 dark:bg-slate-950/80">

    @if (! empty($navUpdateInfo) && $navUpdateInfo['update_available'])
        @php
            $updateLink = $navUpdateInfo['download_url'] ?? route('admin.update.show');
        @endphp
        <div class="relative w-full bg-amber-100/80 text-amber-900 dark:bg-amber-900/30 dark:text-amber-100">
            <div class="mx-auto flex items-center justify-between px-4 py-2 text-sm sm:px-6 lg:px-10">
                <div class="flex items-center gap-2 font-semibold">
                    <span class="inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
                    Update verfügbar: {{ $navUpdateInfo['latest'] }}
                </div>
                <a href="{{ $updateLink }}" class="text-sm font-semibold underline underline-offset-4" @if (! empty($navUpdateInfo['download_url'])) target="_blank" rel="noopener" @endif>
                    ZIP herunterladen
                </a>
            </div>
        </div>
    @endif

    <div class="relative w-full px-4 sm:px-6 lg:px-10">
        <div class="flex h-16 items-center justify-between gap-6">
            <div class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}" class="group hidden sm:flex items-center gap-3">
                    <x-application-logo class="h-10 w-10" />
                    <div class="flex flex-col leading-none">
                        <span class="text-[11px] uppercase tracking-[0.35em] text-gray-500 group-hover:text-gray-700 dark:text-slate-400 dark:group-hover:text-slate-200">Budget</span>
                        <span class="text-lg font-black tracking-tight text-gray-900 dark:text-slate-100">Monatscockpit</span>
                    </div>
                </a>
                <a href="{{ route('dashboard') }}" class="group sm:hidden text-sm font-semibold uppercase tracking-[0.25em] text-gray-500 dark:text-slate-400">Übersicht</a>
            </div>

            <div class="flex items-center gap-2">
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

                <x-dropdown align="right" width="48" content-classes="py-1 bg-white/95 backdrop-blur dark:bg-slate-900/95 dark:text-slate-100">
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
                            <x-dropdown-link :href="route('admin.update.show')">Update</x-dropdown-link>
                        @endcan
                    </x-slot>
                </x-dropdown>

                <x-dropdown align="right" width="48" content-classes="py-1 bg-white/95 backdrop-blur dark:bg-slate-900/95 dark:text-slate-100">
                    <x-slot name="trigger">
                        <button class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[var(--accent)] bg-white/80 dark:bg-slate-900/80 text-[var(--accent)] shadow-sm transition hover:opacity-80 overflow-hidden relative top-[2px]">
                            @php
                                $photoUrl = Auth::user()->profilePhotoUrl();
                            @endphp
                            @if ($photoUrl)
                                <img src="{{ $photoUrl }}" alt="Profilbild" class="h-full w-full object-cover block" />
                            @else
                                <span class="h-full w-full bg-[var(--accent)]/20 flex items-center justify-center text-[10px] font-semibold text-[var(--accent)]">?</span>
                            @endif
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            Profil
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                Abmelden
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
</nav>
