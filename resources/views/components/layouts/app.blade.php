<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Peix Scanner' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>
<body class="h-full font-sans antialiased text-slate-900">
    <div class="min-h-full flex flex-col">
        <header class="bg-slate-900 text-white">
            <div class="mx-auto max-w-screen-sm px-4 py-3 flex items-center justify-between">
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <span class="text-xl font-semibold">Peix Scanner</span>
                </a>
                <span class="text-xs text-slate-300">Yuka del pescado</span>
            </div>
        </header>

        <main class="flex-1 mx-auto w-full max-w-screen-sm px-4 py-6">
            {{ $slot }}
        </main>

        <footer class="mx-auto w-full max-w-screen-sm px-4 py-6 text-center text-xs text-slate-400">
            MVP demo · Peix Scanner
        </footer>
    </div>

    @livewireScripts
</body>
</html>