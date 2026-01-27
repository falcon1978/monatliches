<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Update</h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full px-4 sm:px-6 lg:px-10">
            <div class="bg-white dark:bg-slate-900/80 shadow sm:rounded-lg border accent-box">
                <div class="p-6 space-y-6">
                    <div class="text-sm text-gray-600">
                        Aktuelle Version: <span class="font-semibold">{{ config('update.current_version') }}</span>
                        @if (! empty($updateInfo) && $updateInfo['update_available'])
                            <span class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Update verfügbar</span>
                        @endif
                    </div>

                    @if (session('status'))
                        <div class="border border-green-200 dark:border-emerald-700/60 bg-green-50 dark:bg-emerald-900/30 text-green-800 dark:text-emerald-100 p-3 text-sm accent-box">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->has('update'))
                        <div class="border border-red-200 bg-red-50 text-red-800 p-3 text-sm accent-box">
                            {{ $errors->first('update') }}
                        </div>
                    @endif

                    <div class="rounded-md border border-emerald-100 bg-emerald-50/40 p-4 space-y-3">
                        <div class="text-sm font-semibold text-gray-800">Update prüfen</div>
                        <div class="text-xs text-gray-600">Die Prüfung erfolgt automatisch beim Öffnen dieser Seite.</div>
                        <form method="POST" action="{{ route('admin.update.check') }}" class="flex justify-end">
                            @csrf
                            <x-primary-button>Nach Updates suchen</x-primary-button>
                        </form>

                        @php
                            $info = session('update_info') ?? $updateInfo;
                        @endphp

                        @if (! empty($info))
                            <div class="text-sm text-gray-700 space-y-1">
                                <div>Neueste Version: <span class="font-semibold">{{ $info['latest'] }}</span></div>
                                @if (! empty($info['released_at']))
                                    <div>Release: {{ $info['released_at'] }}</div>
                                @endif
                                @if (! empty($info['changelog']))
                                    <div>Changelog: {{ $info['changelog'] }}</div>
                                @endif
                            </div>

                            @if ($info['update_available'])
                                <form method="POST" action="{{ route('admin.update.download') }}" class="flex justify-end">
                                    @csrf
                                    <x-primary-button>Update herunterladen</x-primary-button>
                                </form>
                                <div class="text-xs text-gray-600">
                                    Danach im Terminal ausführen: <span class="font-mono">php artisan app:apply-update</span>
                                </div>
                            @else
                                <div class="text-sm text-gray-600">Keine Updates verfügbar.</div>
                            @endif
                        @endif
                    </div>

                    <div class="rounded-md border border-gray-200 bg-white/50 p-4 space-y-3">
                        <div class="text-sm text-gray-600">
                            Alternativ: Lade das Dist-ZIP hoch (monatliches-dist-vX.Y.Z.zip). Es wird nur gespeichert.
                        </div>
                        <div class="text-xs text-gray-600">
                            Danach im Terminal ausführen: <span class="font-mono">php artisan app:apply-update</span>
                        </div>

                        <form method="POST" action="{{ route('admin.update.run') }}" enctype="multipart/form-data" class="space-y-4">
                            @csrf

                            <div>
                                <x-input-label for="package" value="Update ZIP (nur Upload)" />
                                <input id="package" name="package" type="file" accept=".zip" class="mt-1 block w-full text-sm" required />
                                <x-input-error :messages="$errors->get('package')" class="mt-2" />
                            </div>

                            <div class="flex justify-end">
                                <x-primary-button>Update hochladen</x-primary-button>
                            </div>
                        </form>
                </div>
            </div>
            </div>
        </div>
    </div>
</x-app-layout>
