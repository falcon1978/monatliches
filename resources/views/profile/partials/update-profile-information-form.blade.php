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
        enctype="multipart/form-data"
        x-data="{
            accent: '{{ $currentAccent }}',
            photo: '{{ $user->profilePhotoUrl() ?? '' }}',
            zoom: 1,
            offsetX: 0,
            offsetY: 0,
            dragging: false,
            startX: 0,
            startY: 0,
            baseScale: 1,
            imgWidth: 1,
            imgHeight: 1,
            cropSize: 80,
            outputSize: 256,
            cropped: '',
            initImage() {
                const img = this.$refs.photoImg;
                if (!img || !this.photo) return;
                const ready = () => {
                    const box = this.$refs.cropBox;
                    if (box?.clientWidth) {
                        this.cropSize = box.clientWidth;
                    }
                    this.imgWidth = img.naturalWidth;
                    this.imgHeight = img.naturalHeight;
                    this.baseScale = Math.max(this.cropSize / img.naturalWidth, this.cropSize / img.naturalHeight);
                    this.zoom = 1;
                    const displayW = this.imgWidth * this.baseScale;
                    const displayH = this.imgHeight * this.baseScale;
                    this.offsetX = (this.cropSize - displayW) / 2;
                    this.offsetY = (this.cropSize - displayH) / 2;
                    this.updateCrop();
                };
                if (img.complete) {
                    ready();
                } else {
                    img.onload = ready;
                }
            },
            loadPhoto(event) {
                const file = event.target.files?.[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.photo = e.target.result;
                    this.$nextTick(() => this.initImage());
                };
                reader.readAsDataURL(file);
            },
            startDrag(event) {
                if (!this.photo) return;
                this.dragging = true;
                this.startX = event.clientX;
                this.startY = event.clientY;
            },
            drag(event) {
                if (!this.dragging) return;
                const dx = event.clientX - this.startX;
                const dy = event.clientY - this.startY;
                this.startX = event.clientX;
                this.startY = event.clientY;
                this.offsetX += dx;
                this.offsetY += dy;
                this.clampOffsets();
                this.updateCrop();
            },
            stopDrag() {
                this.dragging = false;
            },
            clampOffsets() {
                const img = this.$refs.photoImg;
                if (!img) return;
                const scale = this.baseScale * this.zoom;
                const displayW = this.imgWidth * scale;
                const displayH = this.imgHeight * scale;
                const minX = this.cropSize - displayW;
                const minY = this.cropSize - displayH;
                const maxX = 0;
                const maxY = 0;
                this.offsetX = Math.min(maxX, Math.max(minX, this.offsetX));
                this.offsetY = Math.min(maxY, Math.max(minY, this.offsetY));
            },
            updateCrop() {
                const img = this.$refs.photoImg;
                if (!img) return;
                const scale = this.baseScale * this.zoom;
                const originX = this.offsetX;
                const originY = this.offsetY;
                const canvas = document.createElement('canvas');
                canvas.width = this.outputSize;
                canvas.height = this.outputSize;
                const ctx = canvas.getContext('2d');
                if (!ctx) return;
                const sx = (0 - originX) / scale;
                const sy = (0 - originY) / scale;
                const sWidth = this.cropSize / scale;
                const sHeight = this.cropSize / scale;
                ctx.clearRect(0, 0, this.outputSize, this.outputSize);
                ctx.drawImage(img, sx, sy, sWidth, sHeight, 0, 0, this.outputSize, this.outputSize);
                this.cropped = canvas.toDataURL('image/webp', 0.82);
            }
        }"
        x-init="initImage()"
        x-effect="if (photo) { $nextTick(() => initImage()); }"
    >
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
            <x-input-label for="employment_type" value="Beschäftigung" />
            <select id="employment_type" name="employment_type" class="mt-1 block w-full border-gray-300 focus:border-[var(--accent)] focus:ring-[var(--accent)] rounded-md shadow-sm">
                <option value="employed" @selected(old('employment_type', $user->employment_type ?? 'employed') === 'employed')>Angestellt</option>
                <option value="self_employed" @selected(old('employment_type', $user->employment_type ?? 'employed') === 'self_employed')>Selbstständig</option>
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('employment_type')" />
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

        <div>
            <x-input-label for="profile_photo" value="Profilbild" />
            <div class="mt-2 flex flex-wrap items-center gap-4">
                <div
                    x-ref="cropBox"
                    class="relative h-20 w-20 overflow-hidden rounded-full border border-gray-300 bg-white/80"
                    @pointerdown.prevent="startDrag($event)"
                    @pointermove.prevent="drag($event)"
                    @pointerup="stopDrag()"
                    @pointerleave="stopDrag()"
                    @pointercancel="stopDrag()"
                >
                    <template x-if="photo">
                        <div
                            class="absolute inset-0 cursor-grab select-none"
                            :style="`background-image: url(${photo}); background-repeat: no-repeat; background-size: ${imgWidth * baseScale * zoom}px ${imgHeight * baseScale * zoom}px; background-position: ${offsetX}px ${offsetY}px;`"
                        ></div>
                    </template>
                    <template x-if="!photo">
                        <div class="flex h-full w-full items-center justify-center text-xs text-gray-400">Kein Bild</div>
                    </template>
                </div>
                <img x-ref="photoImg" :src="photo" alt="" class="hidden" @load="initImage()">
                <div class="flex-1 min-w-[12rem] space-y-2">
                    <input id="profile_photo" name="profile_photo" type="file" accept="image/*" class="block w-full text-sm text-gray-600 file:mr-4 file:rounded file:border-0 file:bg-[var(--accent)] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:opacity-90" @change="loadPhoto($event)">
                    <div class="flex items-center gap-3">
                        <label class="text-xs text-gray-500">Zoom</label>
                        <input type="range" min="1" max="2.5" step="0.01" x-model.number="zoom" @input="clampOffsets(); updateCrop()" class="w-40">
                        <span class="text-xs text-gray-500" x-text="`${Math.round(zoom * 100)}%`"></span>
                    </div>
                    <p class="text-xs text-gray-500">Ziehe das Bild, um den Ausschnitt festzulegen. Wird als kleines WebP gespeichert.</p>
                </div>
            </div>
            <input type="hidden" name="profile_photo_cropped" x-model="cropped">
            <x-input-error :messages="$errors->get('profile_photo')" class="mt-2" />
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
