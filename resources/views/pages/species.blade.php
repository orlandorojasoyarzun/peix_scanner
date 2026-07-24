<x-layouts.app title="Ficha de especie">
    <div class="flex flex-col gap-6" x-data="{ tab: 'nutricion' }">
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
            <div class="-mx-4">
                <img src="{{ $imageUrl }}" alt="{{ $displayLocal !== '' ? $displayLocal : $scientific }}" class="w-full h-56 object-cover">
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
                @click="tab = 'nutricion'"
                :class="tab === 'nutricion' ? 'bg-ink text-cream shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                class="flex-1 px-2 py-2 text-xs font-medium text-center rounded-lg transition"
            >Nutrición</button>

            <button
                type="button"
                @click="tab = 'sostenibilidad'"
                :class="tab === 'sostenibilidad' ? 'bg-ink text-cream shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                class="flex-1 px-2 py-2 text-xs font-medium text-center rounded-lg transition"
            >Sostenibilidad</button>

            <button
                type="button"
                @click="tab = 'preparacion'"
                :class="tab === 'preparacion' ? 'bg-ink text-cream shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                class="flex-1 px-2 py-2 text-xs font-medium text-center rounded-lg transition"
            >Preparación</button>
        </div>

        <div x-show="tab === 'nutricion'" x-cloak class="rounded-2xl bg-white p-4 shadow-sm border border-slate-100">
            <p class="text-sm text-slate-700">Información nutricional por 100 g.</p>
            <p class="mt-3 text-xs text-slate-400">Se completará cuando conectemos las APIs externas.</p>
        </div>

        <div x-show="tab === 'sostenibilidad'" x-cloak class="rounded-2xl bg-white p-4 shadow-sm border border-slate-100">
            <p class="text-sm text-slate-700">Indicadores de sostenibilidad.</p>
            <p class="mt-3 text-xs text-slate-400">Disponible cuando integremos FishBase y datos MSC.</p>
        </div>

        <div x-show="tab === 'preparacion'" x-cloak class="rounded-2xl bg-white p-4 shadow-sm border border-slate-100">
            <p class="text-sm text-slate-700">Recomendaciones de preparación generadas con IA.</p>
            <p class="mt-3 text-xs text-slate-400">Se completará cuando conectemos la generación de texto.</p>
        </div>

        <a href="{{ route('home') }}" class="text-center text-sm text-slate-500 hover:text-slate-700 mt-2">
            Escanear otro filete
        </a>
    </div>
</x-layouts.app>
