<x-install-layout>
    @include('install.partials.steps', ['step' => 4])

    <div class="mt-6">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Admin-Benutzer</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-slate-300">
            Lege den ersten Admin-Account an. Die Standardkonten werden automatisch erstellt.
        </p>
    </div>

    <div class="mt-4">
        <x-input-error :messages="$errors->get('admin')" class="mb-3" />
    </div>

    <form method="POST" action="{{ route('install.admin.store') }}" class="mt-2 space-y-4">
        @csrf

        <div>
            <x-input-label for="admin_name" value="Name" />
            <x-text-input id="admin_name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="admin_email" value="E-Mail" />
            <x-text-input id="admin_email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="admin_password" value="Passwort" />
            <x-text-input id="admin_password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="admin_password_confirmation" value="Passwort bestätigen" />
            <x-text-input id="admin_password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('install.migrate') }}" class="text-sm text-gray-600 hover:text-gray-900 dark:text-slate-300 dark:hover:text-white">
                Zurück
            </a>
            <x-primary-button>
                Admin anlegen
            </x-primary-button>
        </div>
    </form>
</x-install-layout>
