@php
    /** @var string|null $exception */
    $retryAfter = 60;
@endphp
<x-layouts.app>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-8 text-center">
        <h1 class="text-2xl font-semibold text-amber-900">Demasiadas solicitudes</h1>
        <p class="mt-3 text-amber-800">
            Has hecho muchas peticiones en muy poco tiempo. Por seguridad y para no agotar la cuota del servicio de identificación, limitamos las solicitudes por minuto.
        </p>
        <p class="mt-3 text-sm text-amber-700">
            Inténtalo de nuevo en un minuto.
        </p>
        <a href="{{ route('home') }}"
           class="mt-6 inline-flex items-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
            Volver al inicio
        </a>
    </div>
</x-layouts.app>