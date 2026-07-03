<x-layouts.app title="Peix Scanner — Inicio">
    <div class="flex flex-col items-center text-center gap-6 py-8">
        <div class="w-24 h-24 rounded-full bg-slate-900 text-white flex items-center justify-center text-4xl">
            🐟
        </div>

        <div>
            <h1 class="text-2xl font-bold text-slate-900">Escanea tu filete</h1>
            <p class="mt-2 text-sm text-slate-600">
                Sube una foto del filete de pescado y te diremos qué especie es,
                sus valores nutricionales y cómo prepararlo.
            </p>
        </div>

        <a
            href="{{ route('scan.create') }}"
            class="w-full max-w-xs rounded-2xl bg-slate-900 text-white px-6 py-4 text-base font-semibold shadow-sm hover:bg-slate-800"
        >
            Escanear pescado
        </a>

        <p class="text-xs text-slate-400">
            MVP · Las fotos no se guardan en el servidor
        </p>
    </div>
</x-layouts.app>