@props(['title', 'subtitle' => null, 'back' => null, 'backTitle' => null])

{{-- $back = URL de la pantalla padre → renderiza el <x-volver> canónico a la
     IZQUIERDA del título. $backTitle nombra el destino en el tooltip cuando no
     es obvio ("Volver a la agenda"); el texto visible siempre dice "Volver".
     Es una URL, no una ruta, para que la vista pueda calcularla (ej. el
     conductor de lote/create, que no puede ver el listado de ST y va al Inicio). --}}
<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex min-w-0 items-center gap-3">
        @isset($back)
            <x-volver :href="$back" :titulo="$backTitle" />
        @endisset
        <div class="min-w-0">
            <h2 class="text-xl font-semibold leading-tight text-neutral-900">{{ $title }}</h2>
            @isset($subtitle)
                <p class="mt-1 text-sm text-neutral-500">{{ $subtitle }}</p>
            @endisset
        </div>
    </div>
    @isset($action)
        <div class="shrink-0">{{ $action }}</div>
    @endisset
</div>
