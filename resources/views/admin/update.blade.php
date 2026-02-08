<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">Update</h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box">
                <div class="p-6 space-y-6">
                    <div class="text-sm text-gray-600 dark:text-slate-300">
                        Aktuelle Version: <span class="font-semibold">{{ $installedVersion ?? config('update.current_version') }}</span>
                        @if (! empty($updateInfo) && $updateInfo['update_available'])
                            <span class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-400/20 dark:text-amber-200">Update verfügbar</span>
                        @endif
                    </div>

                    @if ($errors->has('update'))
                        <div class="border border-red-200 bg-red-50 text-red-800 p-3 text-sm accent-box dark:border-red-900/40 dark:bg-red-950/50 dark:text-red-200">
                            {{ $errors->first('update') }}
                        </div>
                    @endif

                    <div class="rounded-md border border-emerald-100 bg-emerald-50/40 p-4 space-y-3 dark:border-emerald-400/20 dark:bg-emerald-950/30">
                        <div class="text-sm font-semibold text-gray-800 dark:text-slate-100">Update prüfen</div>
                        <div class="text-xs text-gray-600 dark:text-slate-300">Die Prüfung erfolgt automatisch beim Öffnen dieser Seite.</div>
                        <form method="POST" action="{{ route('admin.update.check') }}" class="flex justify-end">
                            @csrf
                            <x-primary-button>Nach Updates suchen</x-primary-button>
                        </form>

                        @php
                            $info = session('update_info') ?? $updateInfo;
                        @endphp

                        @if (! empty($info))
                            <div class="text-sm text-gray-700 dark:text-slate-200 space-y-1">
                                <div>Neueste Version: <span class="font-semibold">{{ $info['latest'] }}</span></div>
                                @if (! empty($info['released_at']))
                                    <div>Release: {{ $info['released_at'] }}</div>
                                @endif
                                @if (! empty($info['changelog']))
                                    <div>Changelog: {{ $info['changelog'] }}</div>
                                @endif
                            </div>

                            @if ($info['update_available'])
                                @if (! empty($info['download_url']))
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.update.auto') }}" onsubmit="return confirm('Update jetzt herunterladen und installieren?');">
                                            @csrf
                                            <x-primary-button>Automatisch installieren</x-primary-button>
                                        </form>
                                        <a href="{{ $info['download_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500">
                                            ZIP herunterladen
                                        </a>
                                    </div>
                                @endif
                            @else
                                <div class="text-sm text-gray-600 dark:text-slate-300">Keine Updates verfügbar.</div>
                            @endif
                        @endif
                    </div>

                    <div class="rounded-md border border-gray-200 bg-white/50 p-4 space-y-3 dark:border-slate-700/60 dark:bg-slate-900/60">
                        <div class="text-sm text-gray-600 dark:text-slate-300">
                            Lade das Dist-ZIP hoch (monatliches-dist-vX.Y.Z.zip). Danach wird das Update automatisch installiert.
                        </div>

                        @if (! empty($updateLog))
                            <div class="rounded-md border border-gray-200 bg-white/50 p-4 space-y-2 dark:border-slate-700/60 dark:bg-slate-900/60">
                                <div class="text-sm font-semibold text-gray-800 dark:text-slate-100">Update-Details</div>
                                <pre class="max-h-64 overflow-auto rounded bg-slate-950 text-slate-100 text-xs p-3">{{ $updateLog }}</pre>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.update.run') }}" enctype="multipart/form-data" class="space-y-4">
                            @csrf

                            <div>
                                <x-input-label for="package" value="Update ZIP (Upload)" />
                                <input id="package" name="package" type="file" accept=".zip" class="mt-1 block w-full text-sm" required />
                                <x-input-error :messages="$errors->get('package')" class="mt-2" />
                            </div>

                            <div class="flex justify-end">
                                <x-primary-button>Update installieren</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>
</x-app-layout>
