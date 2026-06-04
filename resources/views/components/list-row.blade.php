{{--
    Fila de lista responsive. En móvil apila (avatar + contenido; meta/acciones en
    una segunda línea). En sm+ vuelve a una sola fila tipo tabla.
    Slots: leading (avatar), meta (badge/conteo), actions (icon-buttons) y el slot por defecto (nombre/contenido).
    El ancho de la columna meta lo define quien la usa (ej. sm:w-28). El ancho de acciones (sm:w-20) va horneado.
--}}
<li {{ $attributes->merge(['class' => 'flex items-start gap-4 px-4 py-4 transition duration-150 hover:bg-neutral-50 sm:items-center sm:px-6']) }}>
    @isset($leading)
        <div class="shrink-0">{{ $leading }}</div>
    @endisset

    <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
        <div class="min-w-0 flex-1">{{ $slot }}</div>

        @if (isset($meta) || isset($actions))
            <div class="flex w-full items-center gap-4 sm:w-auto">
                @isset($meta){{ $meta }}@endisset
                @isset($actions)
                    <div class="ms-auto flex shrink-0 items-center gap-1 sm:ms-0 sm:w-20">{{ $actions }}</div>
                @endisset
            </div>
        @endif
    </div>
</li>
