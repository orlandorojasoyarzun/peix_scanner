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
            class="flex flex-col gap-4"
            x-data="{ preview: null }"
        >
            @csrf

            <label
                for="photo"
                class="flex flex-col items-center justify-center gap-3 border-2 border-dashed border-slate-300 rounded-2xl p-8 bg-white text-center cursor-pointer hover:border-slate-500"
            >
                <template x-if="! preview">
                    <div class="flex flex-col items-center gap-2">
                        <span class="text-4xl">📷</span>
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
                class="rounded-2xl bg-slate-900 text-white px-6 py-4 text-base font-semibold"
            >
                Identificar especie
            </button>
        </form>

        <a href="{{ route('home') }}" class="text-center text-sm text-slate-500">Cancelar</a>
    </div>
</x-layouts.app>