<x-layouts.app title="Escanear pescado">
    <div class="flex flex-col gap-6">
        @if ($errors->any())
            <div class="rounded-2xl border-l-4 border-flame bg-flame/10 p-3">
                <div class="text-xs font-semibold text-ink">Revisa la imagen</div>
                @foreach ($errors->all() as $error)
                    <div class="text-xs text-ink/70 mt-0.5">{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form
            action="{{ route('scan.store') }}"
            method="POST"
            enctype="multipart/form-data"
            class="flex flex-col gap-4 relative"
            x-data="{ preview: null, submitting: false }"
            @submit.prevent="
                const form = $event.target;
                if (form.checkValidity()) {
                    submitting = true;
                    form.submit();
                }
            "
        >
            @csrf

            <label
                for="photo"
                class="flex flex-col items-center justify-center gap-3 border-2 border-dashed border-slate-300 rounded-2xl p-8 bg-white text-center cursor-pointer hover:border-slate-500 transition"
                :class="submitting ? 'opacity-50 pointer-events-none' : ''"
            >
                <template x-if="! preview">
                    <div class="flex flex-col items-center gap-2">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="Camera">
                            <path d="M3 4H8M3 11H9.76389M14.2361 11H21M21 7.2V16.8C21 17.9201 21 18.4802 20.782 18.908C20.5903 19.2843 20.2843 19.5903 19.908 19.782C19.4802 20 18.9201 20 17.8 20H6.2C5.0799 20 4.51984 20 4.09202 19.782C3.71569 19.5903 3.40973 19.2843 3.21799 18.908C3 18.4802 3 17.9201 3 16.8V10.2C3 9.0799 3 8.51984 3.21799 8.09202C3.40973 7.71569 3.71569 7.40973 4.09202 7.21799C4.51984 7 5.0799 7 6.2 7H7.67452C8.1637 7 8.40829 7 8.63846 6.94474C8.84254 6.89575 9.03763 6.81494 9.21657 6.70528C9.4184 6.5816 9.59135 6.40865 9.93726 6.06274L11.0627 4.93726C11.4086 4.59136 11.5816 4.4184 11.7834 4.29472C11.9624 4.18506 12.1575 4.10425 12.3615 4.05526C12.5917 4 12.8363 4 13.3255 4H17.8C18.9201 4 19.4802 4 19.908 4.21799C20.2843 4.40973 20.5903 4.71569 20.782 5.09202C21 5.51984 21 6.0799 21 7.2ZM15 13C15 14.6569 13.6569 16 12 16C10.3431 16 9 14.6569 9 13C9 11.3431 10.3431 10 12 10C13.6569 10 15 11.3431 15 13Z" stroke="#0f172a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="text-sm font-semibold text-slate-700">Toca para fotografiar el pez</span>
                        <span class="text-xs text-slate-500">entero, en el hielo, o el filete</span>
                    </div>
                </template>
                <template x-if="preview">
                    <img :src="preview" class="rounded-xl max-h-72 object-contain" alt="Preview">
                </template>
            </label>

            <input
                id="photo"
                name="photo"
                type="file"
                accept="image/*"
                capture="environment"
                class="hidden"
                required
                @change="
                    const file = $event.target.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = (e) => preview = e.target.result;
                    reader.readAsDataURL(file);
                "
            >

            <button
                type="submit"
                :disabled="submitting"
                class="rounded-2xl bg-slate-900 text-white px-6 py-4 text-base font-semibold disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <span x-show="! submitting" x-cloak>Identificar especie</span>
                <span x-show="submitting" x-cloak class="flex items-center justify-center gap-2">
                    <x-loading label="Identificando" />
                </span>
            </button>

            <div x-show="submitting" x-cloak class="text-center text-xs text-slate-500">
                La IA está mirando tu foto. Esto puede tardar entre 5 y 30 segundos.
            </div>
        </form>

        <a href="{{ route('home') }}" class="text-center text-sm text-slate-500">Cancelar</a>
    </div>
</x-layouts.app>
