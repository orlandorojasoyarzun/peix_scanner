<x-layouts.app title="Escanear">
    <div class="flex flex-col gap-6">
        @if ($errors->any())
            <div class="rounded-2xl border-l-4 border-flame bg-flame/10 p-3">
                <div class="text-xs font-semibold text-ink">Revisa la imagen</div>
                @foreach ($errors->all() as $error)
                    <div class="text-xs text-ink/70 mt-0.5">{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form
            id="scan-form"
            action="{{ route('scan.store') }}"
            method="POST"
            enctype="multipart/form-data"
            class="flex flex-col gap-4"
            data-scan-route="{{ route('scan.store') }}"
            data-scan-label-route="{{ route('scan.storeLabel') }}"
        >
            @csrf
            <input type="hidden" name="scan_type" id="scan_type" value="fish">

            <div class="flex flex-col gap-4">
                <div
                    id="box-fish"
                    class="flex flex-col items-center justify-center gap-3 border-2 border-dashed border-slate-300 rounded-2xl p-6 bg-white text-center cursor-pointer hover:border-emerald-500 hover:bg-emerald-50/30 transition min-h-[180px] max-h-[420px] overflow-hidden"
                >
                    <div class="box-icons flex flex-col items-center gap-3">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="Camera" class="text-slate-600">
                            <path d="M3 4H8M3 11H9.76389M14.2361 11H21M21 7.2V16.8C21 17.9201 21 18.4802 20.782 18.908C20.5903 19.2843 20.2843 19.5903 19.908 19.782C19.4802 20 18.9201 20 17.8 20H6.2C5.0799 20 4.51984 20 4.09202 19.782C3.71569 19.5903 3.40973 19.2843 3.21799 18.908C3 18.4802 3 17.9201 3 16.8V10.2C3 9.0799 3 8.51984 3.21799 8.09202C3.40973 7.71569 3.71569 7.40973 4.09202 7.21799C4.51984 7 5.0799 7 6.2 7H7.67452C8.1637 7 8.40829 7 8.63846 6.94474C8.84254 6.89575 9.03763 6.81494 9.21657 6.70528C9.4184 6.5816 9.59135 6.40865 9.93726 6.06274L11.0627 4.93726C11.4086 4.59136 11.5816 4.4184 11.7834 4.29472C11.9624 4.18506 12.1575 4.10425 12.3615 4.05526C12.5917 4 12.8363 4 13.3255 4H17.8C18.9201 4 19.4802 4 19.908 4.21799C20.2843 4.40973 20.5903 4.71569 20.782 5.09202C21 5.51984 21 6.0799 21 7.2ZM15 13C15 14.6569 13.6569 16 12 16C10.3431 16 9 14.6569 9 13C9 11.3431 10.3431 10 12 10C13.6569 10 15 11.3431 15 13Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <div class="flex flex-col items-center gap-1">
                            <span class="text-sm font-semibold text-slate-700">Escanear pez</span>
                            <span class="text-xs text-slate-500">filete o entero</span>
                        </div>
                    </div>
                </div>

                <div
                    id="box-label"
                    class="flex flex-col items-center justify-center gap-3 border-2 border-dashed border-slate-300 rounded-2xl p-6 bg-white text-center cursor-pointer hover:border-slate-600 hover:bg-slate-50/30 transition min-h-[180px] max-h-[420px] overflow-hidden"
                >
                    <div class="box-icons flex flex-col items-center gap-3">
                        <img src="{{ asset('svg/bill.svg') }}" width="48" height="48" alt="Bill" class="text-slate-600">
                        <div class="flex flex-col items-center gap-1">
                            <span class="text-sm font-semibold text-slate-700">Escanear etiqueta</span>
                            <span class="text-xs text-slate-500">nombre del pescado</span>
                        </div>
                    </div>
                </div>
            </div>

            <input type="file" id="photo" name="photo" accept="image/*" capture="environment" class="hidden">

            <button
                type="submit"
                id="scan-submit"
                class="rounded-2xl bg-emerald-600 text-white px-6 py-3 text-sm font-semibold"
            >
                <span id="scan-submit-label">Escanear</span>
                <span id="scan-submit-busy" class="flex items-center justify-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Escaneando...
                </span>
            </button>

            <div
                id="scan-hint"
                class="hidden text-center text-xs text-slate-500"
            >
                La IA está mirando tu foto. Esto puede tardar entre 5 y 30 segundos.
            </div>
        </form>
    </div>
</x-layouts.app>
