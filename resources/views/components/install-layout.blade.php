<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Monatliches') }} · Installation</title>

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
    <body class="font-sans text-gray-900 dark:text-slate-100 antialiased" style="--accent: #2f6f3e;">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-slate-950">
            <div class="w-full flex items-center justify-end px-6 sm:px-0">
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
            </div>
            <div class="mt-4">
                <x-application-logo class="w-20 h-20" />
            </div>

            <div class="w-full sm:max-w-2xl mt-6 px-6 py-6 bg-white dark:bg-slate-900/80 shadow-md overflow-hidden sm:rounded-lg accent-box border">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
