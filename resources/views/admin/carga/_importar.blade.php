{{--
    IMPORTAR LA CARGA DESDE UNA PLANILLA (pedido del dueño 06-08-2026: «un botón de
    importar en Excel para que se pueda generar una ruta con facturas, cargar y hacer una
    prueba si alcanza todo o no»).

    SE PEGA, no se sube un archivo. Al copiar celdas de Excel el portapapeles trae las
    columnas separadas por TABULADORES, así que se puede leer sin parsear .xlsx y sin
    pedirle al usuario que guarde el archivo, lo busque y lo suba. Es el camino más corto
    a lo que él quiere hacer: probar si la carga alcanza.

    Lee en el cliente (ver `importar()` en el x-data de la pantalla) y deja la lista
    armada en el modo «¿Cabe esta carga?», que es el que responde la pregunta.

    DESDE EL 10-09-2026 TAMBIÉN TRAE FACTURAS (jefe de logística: «exportar documentos a la
    sección de carga del camión para saber su capacidad total»): se busca por folio o
    cliente y las líneas del documento entran convertidas a bultos con el «Cómo viaja» de
    cada producto (su ficha). Lo que no tiene bulto declarado se LISTA —no se salta— y
    viaja en la URL (`origen`, `no_cargadas[]`), porque calcular recarga la página y un
    aviso que viviera solo en este modal moriría justo cuando aparece el veredicto que
    necesita la salvedad. Ver `_aviso-origen.blade.php`.

    Lo que sigue sin hacer, y hay que decirlo: no arma la ruta. Eso engancha con Hojas de
    ruta y es una pieza aparte.
--}}
<div x-show="impAbierto" x-cloak
     class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-neutral-900/40 p-4 sm:items-center"
     x-on:keydown.escape.window="impAbierto = false">

    {{-- Clic afuera cierra; el `stop` de adentro evita que cerrar sea un accidente al
         seleccionar texto del cuadro. --}}
    <div @click="impAbierto = false" class="fixed inset-0"></div>

    <div @click.stop class="relative w-full max-w-lg rounded-2xl border border-neutral-200 bg-white p-4 shadow-xl sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-neutral-900">Traer la carga de una planilla</h2>
                <p class="mt-0.5 text-sm text-neutral-500">Copiá las filas en Excel y pegalas acá.</p>
            </div>
            <button type="button" @click="impAbierto = false"
                    class="rounded-lg p-1 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700"
                    aria-label="Cerrar">✕</button>
        </div>

        <label for="impTexto" class="mt-4 block text-xs font-medium uppercase tracking-wide text-neutral-500">
            Producto y cantidad, uno por línea
        </label>
        <textarea id="impTexto" x-model="impTexto" rows="7"
                  class="mt-1.5 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 font-mono text-sm text-neutral-900 shadow-sm transition duration-150 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                  placeholder="Bolsa 5× botellón 20 L (vacío)&#9;200&#10;Caja de tapas&#9;40&#10;Dispensador LB-07B&#9;12"></textarea>

        <p class="mt-2 text-xs leading-relaxed text-neutral-400">
            Sirve el tabulador de Excel, el punto y coma o la coma. El nombre no necesita estar
            exacto: se busca en el catálogo sin distinguir mayúsculas ni tildes. La última
            columna se toma como la cantidad, en unidades sueltas.
        </p>

        {{-- Lo que NO se pudo leer se muestra TAL CUAL vino: si se descartara en silencio,
             el usuario creería que cargó todo y el veredicto estaría calculado de menos. --}}
        <template x-if="impNoLeidas.length">
            <div class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                <p class="font-semibold" x-text="`No se pudieron leer ${impNoLeidas.length} ${impNoLeidas.length === 1 ? 'línea' : 'líneas'}:`"></p>
                <ul class="mt-1 list-inside list-disc">
                    <template x-for="(l, i) in impNoLeidas.slice(0, 6)" :key="i">
                        <li class="truncate font-mono" x-text="l"></li>
                    </template>
                </ul>
                <p class="mt-1">Revisá que el producto exista en el catálogo y que la cantidad sea un número.</p>
            </div>
        </template>

        {{-- O TRAER UNA FACTURA (jefe de logística, 10-09-2026). Se busca por folio o
             cliente entre los documentos VIGENTES; al elegir uno, sus líneas entran al
             simulador convertidas a bultos según el «Cómo viaja» de cada producto. Lo que no
             se pudo convertir NO se muestra acá sino junto a la lista de la carga (viaja en
             la URL): este modal se cierra y la página se recarga al calcular. --}}
        <div class="mt-5 border-t border-neutral-100 pt-4">
            <label for="docBusca" class="block text-xs font-medium uppercase tracking-wide text-neutral-500">
                O traé una factura
            </label>
            <div class="mt-1.5 flex gap-2">
                <x-text-input id="docBusca" x-model="docBusca" class="block w-full"
                              x-on:keydown.enter.prevent="buscarDocumentos()"
                              placeholder="Folio o nombre del cliente" />
                <x-secondary-button type="button" @click="buscarDocumentos()" ::disabled="!docBusca.trim() || docCargando">
                    Buscar
                </x-secondary-button>
            </div>

            <template x-if="docError">
                <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700" x-text="docError"></p>
            </template>

            <template x-if="docResultados.length">
                <ul class="mt-2 divide-y divide-neutral-100 rounded-lg border border-neutral-200">
                    <template x-for="d in docResultados" :key="d.id">
                        <li>
                            <button type="button" @click="traerDocumento(d.id)" :disabled="docCargando"
                                    class="flex min-h-12 w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm transition hover:bg-neutral-50 disabled:opacity-60">
                                <span class="min-w-0">
                                    <span class="font-medium tabular-nums text-neutral-900" x-text="'N° ' + d.folio"></span>
                                    <span class="ml-1 truncate text-neutral-500" x-text="d.cliente"></span>
                                </span>
                                <span class="shrink-0 text-xs text-neutral-400" x-text="d.emitido"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </template>

            <template x-if="docBuscado && !docResultados.length && !docError">
                <p class="mt-2 text-xs text-neutral-500">No hay documentos vigentes con ese folio o cliente.</p>
            </template>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
            <x-secondary-button type="button" @click="impAbierto = false">Cancelar</x-secondary-button>
            <x-primary-button type="button" @click="importar()" ::disabled="!impTexto.trim()">
                Traer y calcular
            </x-primary-button>
        </div>

        <p class="mt-3 border-t border-neutral-100 pt-3 text-xs leading-relaxed text-neutral-400">
            Lo que no tenga definido «Cómo viaja» en su ficha de producto se avisa junto a la
            lista de la carga y no entra al cálculo. Todavía no arma la ruta: eso engancha con
            Hojas de ruta y es el paso siguiente.
        </p>
    </div>
</div>
