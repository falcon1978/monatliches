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
    <body class="font-sans antialiased text-gray-900 dark:text-slate-100" style="--accent: {{ auth()->user()?->accent_color ?? '#2f6f3e' }};">
        <div class="min-h-screen bg-[#f7f6f1] dark:bg-slate-950 relative overflow-x-hidden">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -right-32 top-24 h-72 w-72 rounded-full bg-emerald-100/60 dark:bg-emerald-500/10 blur-3xl"></div>
                <div class="absolute -left-24 top-96 h-80 w-80 rounded-full bg-amber-100/60 dark:bg-amber-500/10 blur-3xl"></div>
                <div class="absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-white/80 to-transparent dark:from-slate-950/80"></div>
            </div>

            <div class="relative">
                @include('layouts.navigation')
                @if (isset($monthBand))
                    @include('layouts.month-band', ['monthBand' => $monthBand])
                @endif

                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white/90 dark:bg-slate-900/80 shadow backdrop-blur rise-in">
                        <div class="w-full py-6 px-4 sm:px-6 lg:px-10">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main class="rise-in-delay">
                    @if (session('status'))
                        <div class="w-full mt-6 px-4 sm:px-6 lg:px-10" x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition.opacity x-cloak>
                            <div class="bg-green-50 dark:bg-emerald-900/30 border border-green-200 dark:border-emerald-700/60 text-green-800 dark:text-emerald-100 rounded-md px-4 py-3 text-sm">
                                {{ session('status') }}
                            </div>
                        </div>
                    @endif
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
