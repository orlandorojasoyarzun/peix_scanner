@props([
    'label' => 'Procesando',
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-3 text-slate-700']) }}>
    <span class="inline-block text-flame text-2xl leading-none" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24">
            <title>loading</title>
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c4.97 0 9 4.03 9 9">
                <animateTransform attributeName="transform" dur="1.5s" repeatCount="indefinite" type="rotate" values="0 12 12;360 12 12"/>
            </path>
        </svg>
    </span>
    <span class="text-sm font-medium">{{ $label }}</span>
</div>
