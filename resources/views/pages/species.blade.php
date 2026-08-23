<x-layouts.app title="Ficha de especie">
    <div
        class="flex flex-col gap-6"
        data-species-tabs
        data-default-tab="nutricion"
    >
        @php
            $storedResult = Cache::get("species.{$species}.result");
            [$common, $scientific] = array_pad(explode('__', $species, 2), 2, '');
            $common = str_replace('-', ' ', $common);
            $scientific = ucfirst(str_replace('-', ' ', $scientific));

            $displayLocal = '';
            $displayEnglish = '';
            $displayRegional = [];
            $imageUrl = null;
            if (is_array($storedResult)) {
                $displayLocal = (string) ($storedResult['common_name_local'] ?? '');
                $displayEnglish = (string) ($storedResult['common_name'] ?? '');
                $displayRegional = (array) ($storedResult['regional_names'] ?? []);
                $imageUrl = $storedResult['reference_image_url'] ?? null;
            }
        @endphp

        @if ($imageUrl)
            <div class="bg-slate-100 overflow-hidden rounded-2xl flex items-center justify-center" style="max-height: 22rem;">
                <img src="{{ $imageUrl }}" alt="{{ $displayLocal !== '' ? $displayLocal : $scientific }}" class="w-full h-auto max-h-96 object-contain">
            </div>
        @endif

        <div>
            @if ($displayLocal !== '')
                <h1 class="font-display text-3xl leading-tight">{{ $displayLocal }}</h1>
                <p class="font-display text-lg text-slate-700 mt-1">{{ $displayEnglish }}</p>
            @else
                <h1 class="font-display text-3xl leading-tight">{{ ucwords($common) ?: 'Especie' }}</h1>
            @endif
            <p class="font-mono italic text-sm text-slate-500 mt-2">{{ $scientific }}</p>

            @if (! empty($displayRegional) || ($displayEnglish !== '' && strcasecmp($displayEnglish, $displayLocal) !== 0 && strcasecmp($displayEnglish, $common) !== 0))
                <div class="mt-5 rounded-xl border border-slate-200 p-3.5 text-sm">
                    <div class="text-[11px] uppercase tracking-wider text-slate-500 font-bold mb-2">
                        Otros nombres
                    </div>
                    @if ($displayEnglish !== '' && strcasecmp($displayEnglish, $displayLocal) !== 0 && strcasecmp($displayEnglish, $common) !== 0)
                        <div class="text-slate-700">• {{ $displayEnglish }} <span class="text-slate-400 text-xs">(inglés)</span></div>
                    @endif
                    @foreach ($displayRegional as $regionalName)
                        <div class="text-slate-700">• {{ $regionalName }}</div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex gap-1 bg-white rounded-xl p-1 border border-slate-200 mt-2">
            <button
                type="button"
                data-tab-button="nutricion"
                data-tab-active-class="bg-ink text-cream shadow-sm"
                data-tab-inactive-class="text-slate-500 hover:text-slate-700"
                class="flex-1 px-2 py-2 text-xs font-medium text-center rounded-lg transition"
            >Nutrición</button>

            <button
                type="button"
                data-tab-button="parati"
                data-tab-active-class="bg-ink text-cream shadow-sm"
                data-tab-inactive-class="text-slate-500 hover:text-slate-700"
                class="flex-1 px-2 py-2 text-xs font-medium text-center rounded-lg transition"
            >Para ti</button>

            <button
                type="button"
                data-tab-button="sostenibilidad"
                data-tab-active-class="bg-ink text-cream shadow-sm"
                data-tab-inactive-class="text-slate-500 hover:text-slate-700"
                class="flex-1 px-2 py-2 text-xs font-medium text-center rounded-lg transition"
            >Sostenibilidad</button>

            <button
                type="button"
                data-tab-button="preparacion"
                data-tab-active-class="bg-ink text-cream shadow-sm"
                data-tab-inactive-class="text-slate-500 hover:text-slate-700"
                class="flex-1 px-2 py-2 text-xs font-medium text-center rounded-lg transition"
            >Preparación</button>
        </div>

        <div data-tab-panel="nutricion" class="rounded-2xl bg-white p-4 shadow-sm border border-slate-100">
            <p class="text-sm text-slate-700 mb-3">Información nutricional por 100 g.</p>

            @if ($nutrition)
                <div class="space-y-2 text-sm">
                    @if (isset($nutrition['calories']))
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <span class="text-slate-700">Calorías</span>
                            <strong class="font-semibold text-slate-900">{{ number_format((float) $nutrition['calories'], 1) }} kcal</strong>
                        </div>
                    @endif
                    @if (isset($nutrition['protein']))
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <span class="text-slate-700">Proteína</span>
                            <strong class="font-semibold text-slate-900">{{ number_format((float) $nutrition['protein'], 1) }} g</strong>
                        </div>
                    @endif
                    @if (isset($nutrition['fat']))
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <span class="text-slate-700">Grasa</span>
                            <strong class="font-semibold text-slate-900">{{ number_format((float) $nutrition['fat'], 1) }} g</strong>
                        </div>
                    @endif
                    @if (isset($nutrition['omega3']))
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <span class="text-slate-700">Omega-3</span>
                            <strong class="font-semibold text-slate-900">{{ number_format((float) $nutrition['omega3'], 2) }} g</strong>
                        </div>
                    @endif
                </div>

                @php
                    $vitaminLabels = [
                        'A_RAE_ug' => ['Vitamina A', 'µg'],
                        'D_ug' => ['Vitamina D', 'µg'],
                        'E_mg' => ['Vitamina E', 'mg'],
                        'K_ug' => ['Vitamina K', 'µg'],
                        'B3_mg' => ['Vitamina B3 (Niacina)', 'mg'],
                        'B5_mg' => ['Vitamina B5 (Ác. pantoténico)', 'mg'],
                        'B6_mg' => ['Vitamina B6', 'mg'],
                        'B12_ug' => ['Vitamina B12', 'µg'],
                    ];
                    $mineralLabels = [
                        'calcium_mg' => ['Calcio', 'mg'],
                        'iron_mg' => ['Hierro', 'mg'],
                        'magnesium_mg' => ['Magnesio', 'mg'],
                        'phosphorus_mg' => ['Fósforo', 'mg'],
                        'potassium_mg' => ['Potasio', 'mg'],
                        'selenium_ug' => ['Selenio', 'µg'],
                        'sodium_mg' => ['Sodio', 'mg'],
                    ];
                @endphp

                @if (! empty($nutrition['vitamins']))
                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <div class="text-[11px] uppercase tracking-wider text-slate-500 font-bold mb-2">Vitaminas</div>
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                            @foreach ($nutrition['vitamins'] as $key => $value)
                                @if (isset($vitaminLabels[$key]))
                                    <div class="flex justify-between gap-2">
                                        <span class="text-slate-600">{{ $vitaminLabels[$key][0] }}</span>
                                        <span class="text-slate-900 font-medium">{{ number_format((float) $value, 2) }} {{ $vitaminLabels[$key][1] }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($nutrition['minerals']))
                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <div class="text-[11px] uppercase tracking-wider text-slate-500 font-bold mb-2">Minerales</div>
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                            @foreach ($nutrition['minerals'] as $key => $value)
                                @if (isset($mineralLabels[$key]))
                                    <div class="flex justify-between gap-2">
                                        <span class="text-slate-600">{{ $mineralLabels[$key][0] }}</span>
                                        <span class="text-slate-900 font-medium">{{ number_format((float) $value, 2) }} {{ $mineralLabels[$key][1] }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($nutrition['contaminants']['methylmercury_mg_per_kg']))
                    <div class="mt-3 p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-[11px] text-amber-900">
                        <strong>⚠️ Mercurio:</strong> {{ number_format((float) $nutrition['contaminants']['methylmercury_mg_per_kg'], 2) }} mg/kg.
                        @if ($nutrition['contaminants']['methylmercury_mg_per_kg'] >= 0.5)
                            <span class="block mt-0.5">Nivel alto. Limitar consumo en embarazadas y niños.</span>
                        @elseif ($nutrition['contaminants']['methylmercury_mg_per_kg'] >= 0.3)
                            <span class="block mt-0.5">Nivel medio.</span>
                        @endif
                    </div>
                @endif

                @if (! empty($nutrition['note']))
                    <p class="text-[11px] text-slate-500 mt-3 italic">
                        {{ $nutrition['note'] }}
                    </p>
                @endif

                <p class="text-[10px] text-slate-400 mt-3 italic">
                    Fuente: {{ $nutrition['source'] ?? 'USDA FoodData Central' }}
                </p>
            @else
                <p class="mt-1 text-xs text-slate-400">Información nutricional no disponible en este momento.</p>
            @endif
        </div>

        @php
            $explainData = [
                'url_explain' => route('species.explain', $species),
                'csrf_token' => csrf_token(),
                'has_cached_explanation' => ! empty($cached_explanation),
                'cached_explanation' => $cached_explanation ?? '',
            ];
        @endphp

        <div
            data-tab-panel="parati"
            data-explanation-panel
            data-explanation-config='@json($explainData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)'
            class="rounded-2xl bg-white p-4 shadow-sm border border-slate-100"
        >
            <p class="text-sm text-slate-700 mb-3">Toda la información sobre este pescado explicada.</p>

            <div id="recommendations-container">
                @include('partials.recommendations-list', [
                    'recommendations' => $recommendations,
                ])
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100">
                <button
                    type="button"
                    data-explanation-cta
                    class="w-full rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-2.5 text-sm font-medium hover:bg-emerald-100 disabled:opacity-60 inline-flex items-center justify-center gap-2"
                >
                    <span data-explanation-cta-label class="inline-flex items-center gap-2">
                        <span>🪄</span>
                        <span>Quiero una explicación personalizada</span>
                    </span>
                </button>

                <div data-explanation-result class="rounded-xl bg-slate-50 border border-slate-200 p-4 hidden">
                    <div class="text-[11px] uppercase tracking-wider text-slate-500 font-bold mb-2">🪄 Interpretación personalizada</div>
                    <p data-explanation-text class="text-sm text-slate-700 leading-relaxed"></p>
                </div>
            </div>
        </div>

        <div data-tab-panel="sostenibilidad" class="rounded-2xl bg-white p-4 shadow-sm border border-slate-100">
            <p class="text-sm text-slate-700">Estado de la pesquería y recomendaciones de compra responsable.</p>
            <p class="mt-3 text-xs text-slate-400">Se completará cuando conectemos el panel de sostenibilidad.</p>
        </div>

        <div data-tab-panel="preparacion" class="rounded-2xl bg-white p-4 shadow-sm border border-slate-100">
            <p class="text-sm text-slate-700">Recomendaciones de preparación generadas con IA.</p>
            <p class="mt-3 text-xs text-slate-400">Se completará cuando conectemos la generación de texto.</p>
        </div>

        <a href="{{ route('home') }}" class="text-center text-sm text-slate-500 hover:text-slate-700 mt-2">
            Escanear otro filete
        </a>
    </div>
</x-layouts.app>
