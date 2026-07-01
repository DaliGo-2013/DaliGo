{{-- Una métrica "etiqueta + valor" para filas de datos. El valor va PEGADO a la
     etiqueta (legible) dentro de un cell de ANCHO FIJO (`w`) con tabular-nums; el
     ancho fijo del cell mantiene las columnas alineadas entre filas (cada columna
     arranca en el mismo x) y el sobrante queda como separación entre métricas.
     Props: label, w (ancho del cell completo etiqueta+valor, ej. w-28), tone. --}}
@props(['label', 'w' => 'w-16', 'tone' => null])

<span class="{{ $w }} inline-flex items-baseline gap-1 tabular-nums">
    <span class="text-neutral-400">{{ $label }}</span>
    <span class="{{ $tone === 'brand' ? 'font-medium text-brand-600' : ($tone === 'muted' ? 'text-neutral-500' : '') }}">{{ $slot }}</span>
</span>
