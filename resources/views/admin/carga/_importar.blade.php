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

    Lo que NO hace todavía, y hay que decirlo: no lee facturas ni arma la ruta. Eso
    necesita enganchar con Hojas de ruta y es una pieza aparte.
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

        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
            <x-secondary-button type="button" @click="impAbierto = false">Cancelar</x-secondary-button>
            <x-primary-button type="button" @click="importar()" ::disabled="!impTexto.trim()">
                Traer y calcular
            </x-primary-button>
        </div>

        <p class="mt-3 border-t border-neutral-100 pt-3 text-xs leading-relaxed text-neutral-400">
            Todavía no lee facturas ni arma la ruta: eso engancha con Hojas de ruta y es el
            paso siguiente. Por ahora trae productos y cantidades, que es lo que hace falta
            para responder si la carga alcanza.
        </p>
    </div>
</div>
