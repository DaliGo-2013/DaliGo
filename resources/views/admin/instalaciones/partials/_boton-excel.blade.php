{{-- Descarga del Excel de instalaciones (pedido del técnico industrial, 13-08): el
     detalle mes por mes de sus trabajos, que es con lo que le pagan las horas
     extras. Baja EXACTAMENTE lo que se está viendo —los mismos filtros de
     búsqueda, categoría y período— y completo, sin la paginación de 25. Por eso el
     enlace lleva los filtros vigentes ($filtros) y no la ruta pelada.

     ESTÁ EN UN PARTIAL porque se ubica en DOS lugares según lo que se esté viendo
     (dueño 14-08): junto a las tarjetas de año, en la misma línea, para no gastar
     una fila entera en un botón; y suelto abajo cuando esa línea no existe (mes
     abierto, o sin historial todavía). Un partial y no dos copias: el href arma la
     descarga y duplicarlo es la forma de que un día bajen cosas distintas. --}}
<a href="{{ route('admin.instalaciones.excel', array_filter($filtros)) }}"
   class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 active:scale-[0.99]">
    <x-icon.document-text class="h-4 w-4" />
    Descargar Excel
</a>
