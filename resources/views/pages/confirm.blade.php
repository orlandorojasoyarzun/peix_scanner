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
                Compara la foto de referencia con tu {{ ($mode ?? 'fish') === 'label' ? 'etiqueta' : 'foto' }} antes de confirmar.
            </p>
        </div>

        @if ($mode === 'label' && isset($result['label_text']))
        <div class="rounded-2xl bg-white p-4 shadow-sm border border-slate-200">
            <div class="text-xs uppercase tracking-wide text-slate-500">Lo que leímos en la etiqueta</div>
            <div class="text-base font-semibold mt-1 text-slate-900">
                {{ $result['label_text'] }}
            </div>
            <div class="text-xs text-slate-500 mt-2">
                Si la lectura es incorrecta, vuelve a fotografiar la etiqueta con mejor luz.
            </div>
        </div>
        @endif

        @if ($referenceImageUrl)
        <div class="rounded-2xl overflow-hidden bg-slate-100 aspect-square">
            <img src="{{ $referenceImageUrl }}" alt="Referencia" class="w-full h-full object-cover">
        </div>
        @elseif ($imageUrl)
        <div class="rounded-2xl overflow-hidden bg-slate-100 aspect-square">
            <img src="{{ $imageUrl }}" alt="Imagen" class="w-full h-full object-cover">
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
                        style="width: {{ round(($result['confidence'] ?? 0) * 100) }}%"></div>
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

        <form action="{{ route('scan.confirm.store', $scan) }}" method="POST" class="flex flex-col gap-3" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <button
                type="submit"
                :disabled="submitting"
                class="rounded-2xl bg-emerald-600 text-white px-6 py-4 text-base font-semibold disabled:opacity-60 disabled:cursor-not-allowed">
                <span x-show="! submitting" x-cloak>Sí, es este</span>
                <span x-show="submitting" x-cloak class="flex items-center justify-center gap-2">
                    <x-loading label="Generando ficha" />
                </span>
            </button>
        </form>

        <form action="{{ route('scan.rescan', $scan) }}" method="POST" class="text-center">
            @csrf
            <button type="submit" class="text-sm text-slate-500 hover:text-slate-700">
                No es este, reintentar
            </button>
        </form>
        @endif
    </div>
</x-layouts.app>