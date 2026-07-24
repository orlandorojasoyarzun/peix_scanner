<x-layouts.app title="Peix Scanner">
    <div class="flex flex-col items-center text-center gap-6 py-8">
        <div class="w-24 h-24 rounded-full bg-slate-900 flex items-center justify-center">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="Fishing">
                <path d="M16 7a2 2 0 1 0 0-4a2 2 0 0 0 0 4m0 0v10c0 6-10 6-10 0v-4l2 2" stroke="#FFFFFF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        <div>
            <h1 class="text-2xl font-bold text-slate-900">Escanea el pescado del mostrador</h1>
            <p class="mt-2 text-sm text-slate-600">
                Sácale una foto al pez antes de comprarlo.
                Te diremos qué especie es, si es sostenible y cómo prepararlo.
            </p>
        </div>

        <a
            href="{{ route('scan.create') }}"
            class="w-full max-w-xs rounded-2xl bg-slate-900 text-white px-6 py-4 text-base font-semibold shadow-sm hover:bg-slate-800"
        >
            Iniciar
        </a>

        <p class="text-xs text-slate-400">
            Funciona con peces enteros en el mostrador o filetes en bandeja.
        </p>
    </div>
</x-layouts.app>
