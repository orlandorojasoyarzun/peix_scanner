<x-layouts.app title="Ficha de especie">
    <div class="flex flex-col gap-6" x-data="{ tab: 'nutrition' }">
        <div>
            <h1 class="text-2xl font-bold">Ficha de especie</h1>
            <p class="text-sm text-slate-600 mt-1">ID: {{ $species }}</p>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            <button
                type="button"
                @click="tab = 'nutrition'"
                :class="tab === 'nutrition' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500'"
                class="px-3 py-2 text-sm font-semibold border-b-2"
            >Nutrición</button>

            <button
                type="button"
                @click="tab = 'sustainability'"
                :class="tab === 'sustainability' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500'"
                class="px-3 py-2 text-sm font-semibold border-b-2"
            >Sostenibilidad</button>

            <button
                type="button"
                @click="tab = 'preparation'"
                :class="tab === 'preparation' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500'"
                class="px-3 py-2 text-sm font-semibold border-b-2"
            >Preparación</button>
        </div>

        <div x-show="tab === 'nutrition'" class="rounded-2xl bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">Información nutricional por 100 g.</p>
            <p class="mt-3 text-xs text-slate-400">Se completará cuando conectemos las APIs externas.</p>
        </div>

        <div x-show="tab === 'sustainability'" class="rounded-2xl bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">Indicadores de sostenibilidad.</p>
            <p class="mt-3 text-xs text-slate-400">Disponible en Fase 5.</p>
        </div>

        <div x-show="tab === 'preparation'" class="rounded-2xl bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">Recomendaciones de preparación generadas con IA.</p>
            <p class="mt-3 text-xs text-slate-400">Se completará cuando conectemos la generación de texto.</p>
        </div>

        <a href="{{ route('home') }}" class="text-center text-sm text-slate-500">Escanear otro filete</a>
    </div>
</x-layouts.app>