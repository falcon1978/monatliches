<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Profilinformationen
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Aktualisiere deine Profildaten und E-Mail-Adresse.
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    @php
        $accentPresets = \App\Models\User::accentPresets();
        $currentAccent = old('accent_color', $user->accent_color ?? '#2f6f3e');
        if (! in_array($currentAccent, $accentPresets, true)) {
            array_unshift($accentPresets, $currentAccent);
        }
    @endphp

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6"
        x-data="{ accent: '{{ $currentAccent }}' }">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" value="Name" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="E-Mail" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        Deine E-Mail-Adresse ist nicht bestätigt.

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--accent)]">
                            Bestätigung erneut senden
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600" x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition.opacity x-cloak>
                            Ein neuer Bestätigungslink wurde gesendet.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="accent_color" value="Akzentfarbe (Hell + Dunkel optimiert)" />
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <input type="hidden" id="accent_color" name="accent_color" x-model="accent">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($accentPresets as $preset)
                        <button type="button"
                            class="h-9 w-9 rounded-full border border-gray-300 ring-2 ring-transparent transition"
                            :class="accent === '{{ $preset }}' ? 'ring-[var(--accent)]' : ''"
                            style="background-color: {{ $preset }}"
                            @click="accent = '{{ $preset }}'">
                        </button>
                    @endforeach
                </div>
                <div class="text-sm text-gray-500">Aktuell: <span class="font-medium" x-text="accent"></span></div>
            </div>
            <p class="mt-2 text-xs text-gray-500">Nur Farben, die im Darkmode gut lesbar sind.</p>
            <x-input-error :messages="$errors->get('accent_color')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Speichern</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition.opacity
                    x-init="setTimeout(() => show = false, 5000)"
                    x-cloak
                    class="text-sm text-gray-600"
                >Gespeichert.</p>
            @endif
        </div>
    </form>
</section>
