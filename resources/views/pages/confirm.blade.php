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
        <div class="bg-slate-100 overflow-hidden rounded-2xl flex items-center justify-center" style="max-height: 22rem;">
            <img src="{{ $referenceImageUrl }}" alt="Referencia" class="w-full h-auto max-h-96 object-contain">
        </div>
        @elseif ($imageUrl)
        <div class="bg-slate-100 overflow-hidden rounded-2xl flex items-center justify-center" style="max-height: 22rem;">
            <img src="{{ $imageUrl }}" alt="Imagen" class="w-full h-auto max-h-96 object-contain">
        </div>
        @endif

        <div class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-slate-500">Detectado</div>
            @php
                $englishName = (string) ($result['common_name'] ?? '');
                $spanishName = (string) ($result['common_name_local'] ?? '');
                $scientificName = (string) ($result['scientific_name'] ?? '');
                $hasSpanish = $spanishName !== '' && strcasecmp($spanishName, $englishName) !== 0;
            @endphp
            @if ($hasSpanish)
                <div class="text-lg font-semibold mt-1 text-slate-900">
                    {{ $spanishName }}
                </div>
                <div class="text-sm text-slate-500 mt-0.5 capitalize">
                    {{ $englishName }}
                </div>
            @else
                <div class="text-lg font-semibold mt-1 capitalize">
                    {{ $englishName !== '' ? $englishName : '—' }}
                </div>
            @endif
            <div class="text-sm text-slate-600 italic mt-0.5">
                {{ $scientificName !== '' ? $scientificName : '—' }}
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

        <form
            id="confirm-form"
            action="{{ route('scan.confirm.store', $scan) }}"
            method="POST"
            class="flex flex-col gap-3"
        >
            @csrf
            <button
                type="submit"
                id="confirm-submit"
                class="rounded-2xl bg-emerald-600 text-white px-6 py-4 text-base font-semibold"
            >
                <span id="confirm-submit-label">Sí, es este</span>
                <span id="confirm-submit-busy" class="flex items-center justify-center gap-2">
                    <span class="inline-block text-flame text-2xl leading-none" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24">
                            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c4.97 0 9 4.03 9 9">
                                <animateTransform attributeName="transform" dur="1.5s" repeatCount="indefinite" type="rotate" values="0 12 12;360 12 12"/>
                            </path>
                        </svg>
                    </span>
                    <span class="text-sm font-medium">Generando ficha</span>
                </span>
            </button>
        </form>

        <form
            id="rescan-form"
            action="{{ route('scan.rescan', $scan) }}"
            method="POST"
            class="rounded-2xl border border-slate-200 bg-white py-3 px-4"
        >
            @csrf
            <button
                type="submit"
                id="rescan-submit"
                class="w-full text-sm font-semibold text-emerald-700 hover:text-emerald-800 flex items-center justify-center gap-2"
            >
                <span id="rescan-submit-label" class="inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
                        <path d="M3 12a9 9 0 0 1 15-6.7L21 8" />
                        <path d="M21 3v5h-5" />
                        <path d="M21 12a9 9 0 0 1-15 6.7L3 16" />
                        <path d="M3 21v-5h5" />
                    </svg>
                    No es este, reintentar
                </span>
                <span id="rescan-submit-busy" class="inline-flex items-center gap-2">
                    <span class="inline-block text-emerald-700" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24">
                            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c4.97 0 9 4.03 9 9">
                                <animateTransform attributeName="transform" dur="1.5s" repeatCount="indefinite" type="rotate" values="0 12 12;360 12 12"/>
                            </path>
                        </svg>
                    </span>
                    Reintentando con tu imagen...
                </span>
            </button>
        </form>
        @endif
    </div>
</x-layouts.app>