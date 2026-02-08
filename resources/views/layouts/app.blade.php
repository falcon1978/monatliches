<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <script>
            (function () {
                const stored = window.localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (stored === 'dark' || (!stored && prefersDark)) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="icon" href="{{ asset('favicon.ico') }}" data-accent-favicon>
    </head>
    <body
        class="font-sans antialiased text-gray-900 dark:text-slate-100"
        style="--accent: {{ auth()->user()?->accent_color ?? '#2f6f3e' }}; --mobile-header-height: 2.75rem; --mobile-section-height: 3.5rem; --mobile-header-offset: calc(env(safe-area-inset-top) + var(--mobile-header-height)); --mobile-subheader-offset: calc(env(safe-area-inset-top) + var(--mobile-header-height) + var(--mobile-section-height));"
    >
        <div class="min-h-screen bg-[var(--surface-2)] dark:bg-slate-950 relative">
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <div class="absolute -right-32 top-24 h-72 w-72 rounded-full bg-emerald-100/60 dark:bg-emerald-500/10 blur-3xl"></div>
                <div class="absolute -left-24 top-96 h-80 w-80 rounded-full bg-amber-100/60 dark:bg-amber-500/10 blur-3xl"></div>
                <div class="absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-white/80 to-transparent dark:from-slate-950/80"></div>
            </div>

            <div class="relative">
                <div class="hidden sm:block">
                    @include('layouts.navigation')
                </div>
                @if (isset($monthBand))
                    @include('layouts.month-band', ['monthBand' => $monthBand])
                @endif

                @php
                    $mobileTitle = $mobileTitle ?? null;
                    $routeMonth = request()->route('month');
                    if ($routeMonth && ! $routeMonth instanceof \App\Models\Month) {
                        $routeMonth = \App\Models\Month::find($routeMonth);
                    }
                    $routeEntry = request()->route('entry');
                    if ($routeEntry && ! $routeEntry instanceof \App\Models\Entry) {
                        $routeEntry = \App\Models\Entry::find($routeEntry);
                    }
                    if (! $mobileTitle) {
                        if (request()->routeIs('entries.edit') && $routeEntry) {
                            $mobileTitle = match ($routeEntry->type) {
                                'income' => 'Einnahme bearbeiten',
                                'expense' => 'Rechnung bearbeiten',
                                default => 'Fixkosten bearbeiten',
                            };
                        } elseif (request()->routeIs('months.show') && $routeMonth) {
                            $mobileTitle = $routeMonth->name;
                        } elseif (request()->routeIs('months.create')) {
                            $mobileTitle = 'Neuer Monat';
                        } elseif (request()->routeIs('months.edit')) {
                            $mobileTitle = 'Monat bearbeiten';
                        } elseif (request()->routeIs('months.index', 'dashboard')) {
                            $mobileTitle = 'Monatsübersicht';
                        } elseif (request()->routeIs('recurring-templates.create')) {
                            $mobileTitle = 'Neuer wiederkehrender Posten';
                        } elseif (request()->routeIs('recurring-templates.edit')) {
                            $mobileTitle = 'Wiederkehrend bearbeiten';
                        } elseif (request()->routeIs('recurring-templates.*')) {
                            $mobileTitle = 'Wiederkehrende';
                        } elseif (request()->routeIs('accounts.create')) {
                            $mobileTitle = 'Neues Konto';
                        } elseif (request()->routeIs('accounts.edit')) {
                            $mobileTitle = 'Konto bearbeiten';
                        } elseif (request()->routeIs('accounts.*')) {
                            $mobileTitle = 'Konten';
                        } elseif (request()->routeIs('holidays.create')) {
                            $mobileTitle = 'Neue Ferien';
                        } elseif (request()->routeIs('holidays.edit')) {
                            $mobileTitle = 'Ferien bearbeiten';
                        } elseif (request()->routeIs('holidays.*')) {
                            $mobileTitle = 'Ferien';
                        } elseif (request()->routeIs('profile.*')) {
                            $mobileTitle = 'Profil';
                        } elseif (request()->routeIs('admin.users.*')) {
                            $mobileTitle = 'Admin';
                        } else {
                            $mobileTitle = config('app.name', 'Monatliches');
                        }
                    }
                @endphp

                <x-mobile.page-header :title="$mobileTitle" />

                <!-- Page Heading -->
                @isset($header)
                    <header class="hidden sm:block bg-white/90 dark:bg-slate-900/80 shadow backdrop-blur rise-in">
                        <div class="w-full py-6 px-4 sm:px-6 lg:px-10">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main class="rise-in-delay pb-[calc(96px+env(safe-area-inset-bottom))] sm:pb-0">
                    {{ $slot }}
                </main>
            </div>
            <x-toast :message="session('status')" variant="success" />
            <x-mobile.bottom-nav />
        </div>
    </body>
</html>
