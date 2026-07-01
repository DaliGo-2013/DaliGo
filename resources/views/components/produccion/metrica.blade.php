{{-- Una métrica "etiqueta + valor" para filas de datos. El valor va en una caja
     de ancho fijo, alineada a la derecha y con tabular-nums (dígitos de ancho
     uniforme); como la etiqueta es texto constante, cada métrica mide igual en
     todas las filas → las columnas se alinean solas sin importar la magnitud.
     Props: label, w (ancho del valor, ej. w-14), tone (brand | muted | null). --}}
@props(['label', 'w' => 'w-12', 'tone' => null])

<span class="inline-flex items-baseline gap-1">
    <span class="text-neutral-400">{{ $label }}</span>
    <span class="{{ $w }} text-right tabular-nums {{ $tone === 'brand' ? 'font-medium text-brand-600' : ($tone === 'muted' ? 'text-neutral-500' : '') }}">{{ $slot }}</span>
</span>
