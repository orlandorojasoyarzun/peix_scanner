@if (empty($recommendations))
    <p class="mt-1 text-xs text-slate-400">Sin recomendaciones disponibles para esta especie.</p>
@else
    <div class="space-y-3" id="recommendations-list">
        @foreach ($recommendations as $rec)
            <div class="flex gap-3 items-start">
                <div class="text-2xl shrink-0 leading-none">{{ $rec['icon'] }}</div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold {{ $rec['tone'] === 'caution' ? 'text-amber-900' : 'text-slate-900' }}">
                        {{ $rec['title'] }}
                    </div>
                    <p class="text-xs {{ $rec['tone'] === 'caution' ? 'text-amber-800' : 'text-slate-600' }} mt-0.5">
                        {{ $rec['body'] }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>
@endif
