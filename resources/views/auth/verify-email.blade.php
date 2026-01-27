<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Bitte bestätige deine E-Mail-Adresse über den Link, den wir dir gesendet haben. Falls keine Mail angekommen ist, kannst du sie erneut anfordern.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600" x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition.opacity x-cloak>
            Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse gesendet.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    Bestätigung erneut senden
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--accent)]">
                Abmelden
            </button>
        </form>
    </div>
</x-guest-layout>
