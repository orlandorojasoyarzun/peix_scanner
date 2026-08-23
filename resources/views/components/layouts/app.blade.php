<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Peix Scanner' }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Alpine.js bundled locally with a pinned SHA384 hash so the browser
         refuses to execute a tampered copy. CSP enforces this via
         `script-src 'self' <hash>` in SecurityHeaders middleware. --}}
    <script defer
        src="{{ asset('vendor/alpinejs-3.14.9.min.js') }}"
        integrity="sha384-zaqGaLZBjCeXCLnYyKyAJrDaZcsW8AqZN7iDmFDw3TzHJvhjMckT1H52jWWza0w8"
        crossorigin="anonymous"></script>
</head>
<body class="h-full overflow-hidden bg-slate-50">
    <div class="flex flex-col h-full">
        <header class="bg-gradient-to-r from-slate-900 to-slate-800 text-white shrink-0">
            <div class="mx-auto max-w-screen-sm px-4 py-3 flex items-center justify-between">
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <svg fill="#ffffff" viewBox="0 0 256 256" class="w-5 h-5 shrink-0" aria-hidden="true">
                        <path d="M164,76a8,8,0,1,1-8-8A8.00009,8.00009,0,0,1,164,76Zm31.34082,89.146c-26.375,26.314-68.91309,36.78662-126.82812,31.31591Q70.97216,216.572,75.90723,239.145a3.99968,3.99968,0,1,1-7.81446,1.71q-5.23681-23.93481-7.72558-45.25537-21.30175-2.4939-45.22266-7.72852a4,4,0,0,1,1.71094-7.81494q22.54541,4.93433,42.65039,7.397C54.05371,129.562,64.53223,87.03027,90.84375,60.66162a90.82146,90.82146,0,0,1,10.88721-9.302c.02978-.02075.05713-.044.08752-.064,39.37268-28.573,93.29627-20.0227,110.06824-16.39233a12.05213,12.05213,0,0,1,9.209,9.21C225.07715,62.50586,234.9873,125.58984,195.34082,165.146Zm-5.65039-5.66309q1.82337-1.81934,3.4939-3.71265A100.0142,100.0142,0,0,1,100.229,62.81079q-1.8955,1.67835-3.72216,3.50171c-24.834,24.88721-34.49512,65.8291-28.89454,122.043C123.85352,193.97461,164.79492,184.31982,189.69043,159.48291ZM213.27734,45.80615a4.03728,4.03728,0,0,0-3.084-3.084c-15.6709-3.39234-65.61011-11.3728-102.18713,13.991a91.98732,91.98732,0,0,0,91.27795,91.28C224.65332,111.42041,216.66992,61.478,213.27734,45.80615Z"/>
                    </svg>
                    <span class="font-display text-xl font-semibold tracking-tight">Peix Scanner</span>
                </a>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto bg-slate-50 min-h-0">
            <div class="mx-auto w-full max-w-screen-sm px-4 pt-6 pb-20">
                {{ $slot }}
            </div>
        </main>

        {{-- Footer desactivado temporalmente: en móvil pisa el contenido del scan.
             Para reactivarlo, descomentar el bloque siguiente. --}}
        {{-- <footer class="shrink-0 bg-white border-t border-slate-200 py-3">
            <div class="mx-auto max-w-screen-sm px-4 text-center text-xs text-slate-500">
                MVP demo · Peix Scanner
            </div>
        </footer> --}}
    </div>
</body>
</html>