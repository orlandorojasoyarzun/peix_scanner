<x-layouts.app title="Confirmar especie">
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-xl font-bold">¿Es este el pez?</h1>
            <p class="text-sm text-slate-600 mt-1">
                Confirma la detección antes de generar la ficha.
            </p>
        </div>

        <div class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-500">Detectado</div>
            <div class="text-lg font-semibold mt-1">{{ $scan }}</div>
            <div class="text-sm text-slate-600 mt-2">Confianza: —</div>
        </div>

        <form action="{{ route('scan.confirm.store', $scan) }}" method="POST" class="flex flex-col gap-3">
            @csrf
            <button
                type="submit"
                class="rounded-2xl bg-emerald-600 text-white px-6 py-4 text-base font-semibold"
            >
                Sí, es este
            </button>
        </form>

        <a href="{{ route('home') }}" class="text-center text-sm text-slate-500">
            No es este, reintentar
        </a>
    </div>
</x-layouts.app>