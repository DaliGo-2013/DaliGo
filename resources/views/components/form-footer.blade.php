{{-- Pie del formulario: el submit alineado a la derecha (va en el slot).

     Ya NO lleva "Cancelar" (doctrina del botón único, 24-07): la única salida de
     un formulario es el <x-volver> del encabezado. Este "Cancelar" de texto y la
     X de <x-form-actions> hacían lo mismo y se repartían las pantallas de CRUD
     casi por mitades, sin regla que lo explicara. --}}
<div class="flex items-center justify-end gap-4 pt-2">
    {{ $slot }}
</div>
