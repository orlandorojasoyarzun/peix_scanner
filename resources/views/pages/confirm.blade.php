<x-layouts.app title="Confirmar especie">
    <div class="flex flex-col gap-6">
        @if ($error)
            <div class="rounded-2xl border-l-4 border-flame bg-flame/10 p-4">
                <div class="text-xs font-semibold text-ink">No pudimos identificar el pez</div>
                <div class="text-xs text-ink/70 mt-1">{{ $error }}</div>
                <a href="{{ route('home') }}" class="text-xs text-slate-700 underline mt-3 inline-block">Volver a empezar</a>
            </div>
        @else
            <div>
                <h1 class="text-xl font-bold">¿Es este el pez?</h1>
                <p class="text-sm text-slate-600 mt-1">
                    Confirma la detección antes de generar la ficha con info nutricional y sostenibilidad.
                </p>
            </div>

            @if ($imageUrl)
                <div class="rounded-2xl overflow-hidden bg-slate-100">
                    <img src="{{ $imageUrl }}" alt="Filete escaneado" class="w-full h-auto max-h-72 object-cover">
                </div>
            @endif

            <div class="rounded-2xl bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">Detectado</div>
                <div class="text-lg font-semibold mt-1 capitalize">
                    {{ $result['common_name'] ?? '—' }}
                </div>
                <div class="text-sm text-slate-600 italic mt-0.5">
                    {{ $result['scientific_name'] ?? '—' }}
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <span class="text-xs text-slate-500">Confianza</span>
                    <div class="flex-1 h-1.5 rounded-full bg-slate-200 overflow-hidden">
                        <div
                            class="h-full rounded-full {{ ($result['confidence'] ?? 0) >= 0.75 ? 'bg-emerald-500' : (($result['confidence'] ?? 0) >= 0.5 ? 'bg-amber-500' : 'bg-rose-500') }}"
                            style="width: {{ round(($result['confidence'] ?? 0) * 100) }}%"
                        ></div>
                    </div>
                    <span class="text-sm font-semibold text-slate-900">
                        {{ round(($result['confidence'] ?? 0) * 100) }}%
                    </span>
                </div>
                @if (! ($result['high_confidence'] ?? false))
                    <div class="text-xs text-amber-700 mt-2">
                        Confianza baja. Te recomendamos verificar visualmente.
                    </div>
                @endif
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
        @endif
    </div>
</x-layouts.app>