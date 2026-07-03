<x-layouts.app title="Escanear pescado">
    <div class="flex flex-col gap-6">
        <h1 class="text-xl font-bold">Foto del filete</h1>

        <form action="{{ route('scan.store') }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-4">
            @csrf

            <label
                for="photo"
                class="flex flex-col items-center justify-center gap-3 border-2 border-dashed border-slate-300 rounded-2xl p-8 bg-white text-center cursor-pointer hover:border-slate-500"
            >
                <span class="text-4xl">📷</span>
                <span class="text-sm font-semibold text-slate-700">Toca para abrir la cámara</span>
                <span class="text-xs text-slate-500">o elige una foto de la galería</span>
            </label>

            <input
                id="photo"
                name="photo"
                type="file"
                accept="image/*"
                capture="environment"
                class="hidden"
                required
            >

            <div id="preview" class="hidden">
                <img id="preview-img" class="rounded-2xl w-full" alt="Preview">
            </div>

            <button
                type="submit"
                class="rounded-2xl bg-slate-900 text-white px-6 py-4 text-base font-semibold"
            >
                Identificar especie
            </button>
        </form>

        <a href="{{ route('home') }}" class="text-center text-sm text-slate-500">Cancelar</a>
    </div>

    <script>
        const input = document.getElementById('photo');
        const preview = document.getElementById('preview');
        const previewImg = document.getElementById('preview-img');

        input.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (ev) => {
                previewImg.src = ev.target.result;
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        });
    </script>
</x-layouts.app>